import sys
import os
import re

from PyPDF2 import PdfReader

# Always resolve to absolute paths
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

def extract_text(file_path):
    text = ""
    if file_path.lower().endswith(".pdf"):
        reader = PdfReader(file_path)
        for page in reader.pages:
            text += page.extract_text() or ""
    else:
        with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
            text = f.read()
    return text


SKILL_LIST = [
    "java","python","html","css","javascript","flutter","php","mysql","sql",
    "react","node","git","docker","c","c++","csharp","django","linux"
]

def parse_skills(text):
    text = text.lower()
    found = []
    for skill in SKILL_LIST:
        if re.search(r"\b" + re.escape(skill) + r"\b", text):
            found.append(skill)
    return ", ".join(found)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("")
        sys.exit(0)

    file_path = os.path.abspath(sys.argv[1])

    if not os.path.exists(file_path):
        print("")
        sys.exit(0)

    text = extract_text(file_path)
    skills = parse_skills(text)

    print(skills)
