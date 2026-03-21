# FILE: python/resume_parser.py

import sys
import re
import json
import PyPDF2
import docx

# Extended skills database (add more as needed)
SKILLS_DATABASE = [
    # Programming Languages
    'python', 'java', 'javascript', 'php', 'ruby', 'c++', 'c#', 'go', 'rust', 'swift',
    'kotlin', 'scala', 'r', 'matlab', 'perl', 'typescript', 'dart',
    
    # Web Frontend
    'html', 'css', 'react', 'angular', 'vue', 'vue.js', 'svelte', 'jquery', 'bootstrap',
    'tailwind', 'sass', 'less', 'webpack', 'redux', 'next.js', 'nuxt.js',
    
    # Web Backend
    'node.js', 'express', 'django', 'flask', 'spring', 'spring boot', 'laravel', 'rails',
    'asp.net', 'fastapi', 'nestjs', 'graphql', 'rest api', 'microservices',
    
    # Databases
    'mysql', 'postgresql', 'mongodb', 'redis', 'elasticsearch', 'cassandra', 'oracle',
    'sql server', 'sqlite', 'dynamodb', 'firebase', 'mariadb', 'sql', 'nosql',
    
    # Cloud & DevOps
    'aws', 'azure', 'gcp', 'google cloud', 'docker', 'kubernetes', 'jenkins', 'gitlab',
    'github', 'ci/cd', 'terraform', 'ansible', 'vagrant', 'nginx', 'apache',
    
    # Data Science & AI
    'machine learning', 'deep learning', 'nlp', 'tensorflow', 'pytorch', 'keras',
    'scikit-learn', 'pandas', 'numpy', 'opencv', 'data analysis', 'big data',
    
    # Mobile Development
    'android', 'ios', 'react native', 'flutter', 'xamarin', 'ionic',
    
    # Other Tools & Concepts
    'git', 'agile', 'scrum', 'jira', 'trello', 'linux', 'unix', 'bash',
    'api', 'oop', 'design patterns', 'testing', 'unit testing', 'tdd'
]

def extract_text_from_pdf(file_path):
    """Extract text from PDF file"""
    text = ""
    try:
        with open(file_path, 'rb') as file:
            pdf_reader = PyPDF2.PdfReader(file)
            for page in pdf_reader.pages:
                page_text = page.extract_text()
                if page_text:
                    text += page_text + "\n"
    except Exception as e:
        print(f"Error reading PDF: {e}", file=sys.stderr)
    return text

def extract_text_from_docx(file_path):
    """Extract text from DOCX file"""
    text = ""
    try:
        doc = docx.Document(file_path)
        for para in doc.paragraphs:
            text += para.text + "\n"
    except Exception as e:
        print(f"Error reading DOCX: {e}", file=sys.stderr)
    return text

def extract_text_from_txt(file_path):
    """Extract text from TXT file"""
    try:
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as file:
            return file.read()
    except Exception as e:
        print(f"Error reading TXT: {e}", file=sys.stderr)
        return ""

def extract_text(file_path):
    """Main function to extract text based on file extension"""
    file_path_lower = file_path.lower()
    
    if file_path_lower.endswith('.pdf'):
        return extract_text_from_pdf(file_path)
    elif file_path_lower.endswith('.docx') or file_path_lower.endswith('.doc'):
        return extract_text_from_docx(file_path)
    elif file_path_lower.endswith('.txt'):
        return extract_text_from_txt(file_path)
    else:
        return ""

def extract_skills(text):
    """Extract skills from resume text"""
    text_lower = text.lower()
    found_skills = []
    
    # Search for each skill in the database
    for skill in SKILLS_DATABASE:
        # Use word boundaries to avoid partial matches
        pattern = r'\b' + re.escape(skill.lower()) + r'\b'
        if re.search(pattern, text_lower):
            found_skills.append(skill)
    
    # Remove duplicates and return
    return list(set(found_skills))

def calculate_score(extracted_skills, required_skills):
    """
    Calculate matching score between extracted and required skills
    Returns a score between 0-100
    """
    if not required_skills or not required_skills.strip():
        return 50.0  # Default score if no requirements
    
    # Parse required skills (comma-separated)
    required_list = [s.strip().lower() for s in required_skills.split(',')]
    extracted_lower = [s.lower() for s in extracted_skills]
    
    if not required_list:
        return 50.0
    
    # Count exact and partial matches
    exact_matches = 0
    partial_matches = 0
    
    for req_skill in required_list:
        # Check for exact match
        if req_skill in extracted_lower:
            exact_matches += 1
        else:
            # Check for partial match (e.g., "python" in "python developer")
            for ext_skill in extracted_lower:
                if req_skill in ext_skill or ext_skill in req_skill:
                    partial_matches += 1
                    break
    
    # Calculate base score (70% weight for required skills match)
    total_required = len(required_list)
    base_score = ((exact_matches * 1.0 + partial_matches * 0.7) / total_required) * 70
    
    # Bonus points for additional relevant skills (30% weight)
    additional_skills = len(extracted_skills) - (exact_matches + partial_matches)
    bonus_score = min(additional_skills * 3, 30)  # Max 30 bonus points
    
    final_score = min(base_score + bonus_score, 100)
    
    return round(final_score, 2)

def extract_email(text):
    """Extract email address from text"""
    email_pattern = r'\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b'
    emails = re.findall(email_pattern, text)
    return emails[0] if emails else None

def extract_phone(text):
    """Extract phone number from text"""
    phone_pattern = r'[\+\(]?[1-9][0-9 .\-\(\)]{8,}[0-9]'
    phones = re.findall(phone_pattern, text)
    return phones[0] if phones else None

def main():
    """Main function to process resume"""
    if len(sys.argv) < 3:
        result = {
            "error": "Usage: python resume_parser.py <resume_file> <required_skills>",
            "skills": [],
            "score": 0
        }
        print(json.dumps(result))
        sys.exit(1)
    
    resume_file = sys.argv[1]
    required_skills = sys.argv[2]
    
    # Extract text from resume
    text = extract_text(resume_file)
    
    if not text or len(text.strip()) < 50:
        result = {
            "error": "Could not extract sufficient text from resume. File might be corrupted or image-based PDF.",
            "skills": [],
            "score": 0
        }
        print(json.dumps(result))
        sys.exit(1)
    
    # Extract skills
    extracted_skills = extract_skills(text)
    
    # Calculate score
    score = calculate_score(extracted_skills, required_skills)
    
    # Extract additional info
    email = extract_email(text)
    phone = extract_phone(text)
    
    # Prepare result
    result = {
        "success": True,
        "skills": extracted_skills,
        "skills_count": len(extracted_skills),
        "score": score,
        "text_length": len(text),
        "email": email,
        "phone": phone
    }
    
    # Output as JSON
    print(json.dumps(result, indent=2))

if __name__ == "__main__":
    main()