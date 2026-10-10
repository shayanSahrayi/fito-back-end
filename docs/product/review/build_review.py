# -*- coding: utf-8 -*-
"""Build the pre-development review HTML from the product docs."""

from pathlib import Path
import html
import re

ROOT = Path(__file__).resolve().parents[1]
REVIEW = Path(__file__).resolve().parent
OUT = REVIEW / "fito-design-review.html"

APPENDIX = [
    ("پیوست الف. نمودار موجودیت‌ها", ROOT / "03-erd.md"),
    ("پیوست ب. فرهنگ داده", ROOT / "02-database.md"),
    ("پیوست ج. سناریوهای داده‌ای", ROOT / "04-scenarios.md"),
    ("پیوست د. وضعیت مخزن و تصمیم‌های باز", ROOT / "05-current-state-and-decisions.md"),
    ("پیوست هـ. نقشه اجرای تفصیلی", ROOT / "06-roadmap.md"),
]


def inline(text: str) -> str:
    escaped = html.escape(text, quote=False)
    escaped = re.sub(r"`([^`]+)`", r"<code>\1</code>", escaped)
    escaped = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", escaped)
    escaped = re.sub(
        r"\[([^\]]+)\]\(([^)]+)\)",
        r'<span class="ref">\1</span>',
        escaped,
    )
    return escaped


def convert_markdown(md: str) -> str:
    lines = md.replace("\r\n", "\n").split("\n")
    out = []
    i = 0
    while i < len(lines):
        line = lines[i]
        if line.startswith("```"):
            lang = line[3:].strip()
            block = []
            i += 1
            while i < len(lines) and not lines[i].startswith("```"):
                block.append(lines[i])
                i += 1
            i += 1
            body = "\n".join(block)
            if lang == "mermaid":
                out.append('<div class="diagram"><pre class="mermaid">')
                out.append(html.escape(body))
                out.append("</pre></div>")
            else:
                out.append('<pre class="code">' + html.escape(body) + "</pre>")
            continue
        if line.startswith("|") and i + 1 < len(lines) and re.match(r"^\|\s*-+", lines[i + 1]):
            rows = []
            while i < len(lines) and lines[i].startswith("|"):
                rows.append(lines[i])
                i += 1
            parsed = []
            for raw in rows:
                cells = [c.strip() for c in raw.strip().strip("|").split("|")]
                parsed.append(cells)
            header, body_rows = parsed[0], parsed[2:]
            out.append('<table><thead><tr>')
            for cell in header:
                out.append(f"<th>{inline(cell)}</th>")
            out.append("</tr></thead><tbody>")
            for row in body_rows:
                out.append("<tr>")
                for cell in row:
                    out.append(f"<td>{inline(cell)}</td>")
                out.append("</tr>")
            out.append("</tbody></table>")
            continue
        if line.startswith("### "):
            out.append(f"<h3>{inline(line[4:])}</h3>")
        elif line.startswith("## "):
            out.append(f"<h2>{inline(line[3:])}</h2>")
        elif line.startswith("# "):
            out.append(f"<h2>{inline(line[2:])}</h2>")
        elif line.startswith("- "):
            items = []
            while i < len(lines) and lines[i].startswith("- "):
                items.append(f"<li>{inline(lines[i][2:])}</li>")
                i += 1
            out.append("<ul>" + "".join(items) + "</ul>")
            continue
        elif re.match(r"^\d+\. ", line):
            items = []
            while i < len(lines) and re.match(r"^\d+\. ", lines[i]):
                item = re.sub(r"^\d+\. ", "", lines[i])
                items.append(f"<li>{inline(item)}</li>")
                i += 1
            out.append("<ol>" + "".join(items) + "</ol>")
            continue
        elif line.strip() == "":
            pass
        else:
            para = [line]
            i += 1
            while i < len(lines) and lines[i].strip() and not lines[i].startswith(("#", "|", "-", "```")) and not re.match(r"^\d+\. ", lines[i]):
                para.append(lines[i])
                i += 1
            out.append(f"<p>{inline(' '.join(para))}</p>")
            continue
        i += 1
    return "\n".join(out)


def main() -> None:
    front = (REVIEW / "front.html").read_text(encoding="utf-8")
    appendices = []
    for title, path in APPENDIX:
        body = convert_markdown(path.read_text(encoding="utf-8"))
        appendices.append(f'<section class="appendix"><h1>{html.escape(title)}</h1>{body}</section>')
    css = (REVIEW / "review.css").read_text(encoding="utf-8")
    doc = f"""<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>بررسی طراحی بک‌اند فیتو</title>
<style>
{css}
</style>
</head>
<body>
{front}
{''.join(appendices)}
<script src="mermaid.min.js"></script>
<script>
mermaid.initialize({{
  startOnLoad: false,
  theme: "base",
  securityLevel: "loose",
  fontFamily: "Vazirmatn, Tahoma, sans-serif",
  themeVariables: {{
    fontFamily: "Vazirmatn, Tahoma, sans-serif",
    primaryColor: "#e7f2f2",
    primaryTextColor: "#1c1917",
    primaryBorderColor: "#0f3d3e",
    lineColor: "#44403c",
    fontSize: "15px"
  }}
}});
var nodes = document.querySelectorAll("pre.mermaid");
var chain = Promise.resolve();
nodes.forEach(function (node) {{
  chain = chain.then(function () {{
    return mermaid.run({{ nodes: [node] }}).catch(function (error) {{
      node.insertAdjacentHTML("afterend", "<p class=warn>نمودار این بخش در خروجی رسم نشد.</p>");
      console.error(error);
    }});
  }});
}});
chain.then(function () {{
  document.querySelectorAll(".diagram svg").forEach(function (svg) {{
    try {{
      const box = svg.getBBox();
      if (box && box.width > 0 && box.height > 0) {{
        const pad = 12;
        svg.setAttribute("viewBox", (box.x - pad) + " " + (box.y - pad) + " " + (box.width + pad * 2) + " " + (box.height + pad * 2));
        svg.setAttribute("width", String(box.width + pad * 2));
        svg.setAttribute("height", String(box.height + pad * 2));
      }}
    }} catch (error) {{
      console.error(error);
    }}
  }});
  document.documentElement.setAttribute("data-rendered", "yes");
}});
</script>
</body>
</html>
"""
    OUT.write_text(doc, encoding="utf-8")
    print(OUT)


if __name__ == "__main__":
    main()
