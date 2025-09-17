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
    """Extract education level from resume text with enhanced detection"""
    # Convert to lowercase for case-insensitive matching
    text_lower = text.lower()
    
    # First, create a repaired version without spaces (like the skills function does)
    repaired_text = ''.join(text.split()).lower()
    
    # Enhanced education keywords with multiple variations
    # Ordered from highest to lowest to prevent early, incorrect matches
    education_patterns = [
        # PhD patterns
        {
            'level': 'PhD',
            'patterns': [
                r'ph\.?d', r'doctorate', r'doctoral', r'phd'
            ]
        },
        # Masters patterns
        {
            'level': 'Masters',
            'patterns': [
                r'master', r'masters', r'm\.tech', r'mtech', 
                r'mba', r'mca', r'ms\b', r'm\.s', r'm\.sc', 
                r'msc', r'm\.e', r'me\b', r'masterofcomputerapplications'
            ]
        },
        # Bachelors patterns
        {
            'level': 'Bachelors',
            'patterns': [
                r'bachelor', r'bachelors', r'b\.tech', r'btech',
                r'b\.e', r'be\b', r'b\.sc', r'bsc', r'bca',
                r'b\.a', r'ba\b', r'b\.com', r'bcom', r'bachelorofcomputerapplications'
            ]
        },
        # Diploma patterns
        {
            'level': 'Diploma',
            'patterns': [
                r'diploma', r'certificate', r'associate'
            ]
        }
    ]
    
    # Check each education level in both original text and repaired text
    for education_group in education_patterns:
        for pattern in education_group['patterns']:
            # Check in original text with word boundaries
            if re.search(r'\b' + pattern + r'\b', text_lower):
                return education_group['level']
            # Check in repaired text (without spaces)
            if re.search(pattern, repaired_text):
                return education_group['level']
    
    return 'Not specified'

def extract_experience(text):
    """Extract experience years from resume text"""
    text_lower = text.lower()
    
    # Patterns to match experience mentions
    experience_patterns = [
        r'(\d+)[\s\-+]*year[s]?[\s]*(?:of\s*)?experience',
        r'experience[\s]*:?[\s]*(\d+)[\s]*year[s]?',
        r'(\d+)[\s]*yr[s]?[\s]*(?:of\s*)?experience',
        r'(\d+)[\s]*\+?[\s]*years?[\s]*in',
        r'over[\s]*(\d+)[\s]*years?',
        r'more\s*than\s*(\d+)\s*years?'
    ]
    
    years = []
    for pattern in experience_patterns:
        matches = re.finditer(pattern, text_lower)
        for match in matches:
            years.append(int(match.group(1)))
    
    # Return the maximum years found, or 0 if none found
    return max(years) if years else 0

def analyze_resume(pdf_path):
    """Main function to analyze resume and return structured data"""
    text = extract_text_from_pdf(pdf_path)
    
    if text.startswith("Error"):
        return {"error": text}
    
    # Debug: Log the extracted text (first 500 chars)
    debug_text = text[:500] + "..." if len(text) > 500 else text
    print(f"DEBUG: Extracted text preview: {debug_text}", file=sys.stderr)
    
    skills = extract_skills(text)
    experience = extract_experience(text)
    education = extract_education(text)
    
    # Debug: Log what we found
    print(f"DEBUG: Found skills: {skills}", file=sys.stderr)
    print(f"DEBUG: Found experience: {experience} years", file=sys.stderr)
    print(f"DEBUG: Found education: {education}", file=sys.stderr)
    
    result = {
        "skills": skills,
        "experience_years": experience,
        "education_level": education,
    }
    
    return result

def main():
    """Main function for command line usage"""
    if len(sys.argv) != 2:
        error_result = {"error": "Usage: python resume_parser.py <pdf_path>"}
        print(json.dumps(error_result))
        sys.exit(1)
    
    pdf_path = sys.argv[1]
    result = analyze_resume(pdf_path)
    
    # Output only the JSON result to stdout
    print(json.dumps(result, indent=2))

if __name__ == "__main__":
    main()