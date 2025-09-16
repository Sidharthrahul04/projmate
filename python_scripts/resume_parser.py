#!/usr/bin/env python3
# resume_parser.py - Extract and analyze resume content

import sys
import json
import re
from pypdf import PdfReader
from textblob import TextBlob

def extract_text_from_pdf(pdf_path):
    """Extract text from PDF file"""
    try:
        reader = PdfReader(pdf_path)
        text = ""
        for page in reader.pages:
            text += page.extract_text() + "\n"
        return text.strip()
    except Exception as e:
        return f"Error reading PDF: {str(e)}"

def extract_skills(text):
    """
    Extract skills by first repairing the text (removing extra spaces)
    and then matching against a keyword list.
    """
    # --- START: TEXT REPAIR LOGIC ---
    # This removes all whitespace (spaces, newlines, tabs) from the text
    # It turns "P y t h o n" into "Python"
    repaired_text = ''.join(text.split()).lower()
    # --- END: TEXT REPAIR LOGIC ---

    skill_keywords = [
        'php', 'python', 'javascript', 'java', 'c', 'c++', 'c#', 'html', 'css', 
        'sql', 'mysql', 'postgresql', 'mongodb', 'pl/sql', 'react', 'angular', 'vue', 'node.js',
        'django', 'flask', 'laravel', 'spring', 'git', 'docker', 'kubernetes', 'bash', 'vs code',
        'aws', 'azure', 'machine learning', 'deep learning', 'data science', 'ai', 'tensorflow', 'opencv',
        'mobile development', 'android', 'ios', 'flutter', 'react native', 'textblob'
    ]
    
    found_skills = set()  # Use a set to automatically handle duplicates

    # Sort keywords by length (longest first) to prevent partial matches
    # e.g., match "javascript" before "java"
    sorted_keywords = sorted(skill_keywords, key=len, reverse=True)

    for skill in sorted_keywords:
        # Remove spaces from the keyword for matching in the repaired_text
        skill_no_space = skill.replace(" ", "")
        
        if skill_no_space in repaired_text:
            found_skills.add(skill.title())
    
    return list(found_skills)

def extract_education(text):
    """Extract education level from resume text"""
    # Ordered from highest to lowest to prevent early, incorrect matches
    education_keywords = {
        'phd': 'PhD', 'doctorate': 'PhD', 'ph.d': 'PhD',
        'master': 'Masters', 'masters': 'Masters', 'm.tech': 'Masters', 'mtech': 'Masters', 'mba': 'Masters', 'mca': 'Masters',
        'bachelor': 'Bachelors', 'btech': 'Bachelors', 'b.tech': 'Bachelors', 'be': 'Bachelors', 'b.e': 'Bachelors', 'bsc': 'Bachelors', 'b.sc': 'Bachelors', 'bca': 'Bachelors'
    }
    
    text_lower = text.lower()
    
    for keyword, level in education_keywords.items():
        # Use word boundaries to ensure we're matching whole words
        if re.search(r'\b' + re.escape(keyword) + r'\b', text_lower):
            return level
    
    return 'Not specified'

def analyze_resume(pdf_path):
    """Main function to analyze resume and return structured data"""
    text = extract_text_from_pdf(pdf_path)
    
    if text.startswith("Error"):
        return {"error": text}
    
    skills = extract_skills(text)
    # Experience extraction is difficult with this text format, can be improved later
    experience = 0 
    education = extract_education(text)
    
    return {
        "skills": skills,
        "experience_years": experience,
        "education_level": education,
    }

def main():
    """Main function for command line usage"""
    if len(sys.argv) != 2:
        print(json.dumps({"error": "Usage: python resume_parser.py <pdf_path>"}))
        sys.exit(1)
    
    pdf_path = sys.argv[1]
    result = analyze_resume(pdf_path)
    
    print(json.dumps(result, indent=2))

if __name__ == "__main__":
    main()