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
    """Extract skills from resume text using keyword matching"""
    # Common technical skills keywords
    skill_keywords = [
        'php', 'python', 'javascript', 'java', 'c++', 'c#', 'html', 'css', 
        'mysql', 'postgresql', 'mongodb', 'react', 'angular', 'vue', 'node',
        'django', 'flask', 'laravel', 'spring', 'git', 'docker', 'kubernetes',
        'aws', 'azure', 'machine learning', 'data science', 'ai', 'blockchain',
        'mobile development', 'android', 'ios', 'flutter', 'react native'
    ]
    
    text_lower = text.lower()
    found_skills = []
    
    for skill in skill_keywords:
        if skill.lower() in text_lower:
            found_skills.append(skill.title())
    
    return list(set(found_skills))  # Remove duplicates

def extract_experience(text):
    """Extract years of experience from resume text"""
    # Regex patterns to find experience mentions
    patterns = [
        r'(\d+)\+?\s*years?\s+(?:of\s+)?experience',
        r'experience:?\s*(\d+)\+?\s*years?',
        r'(\d+)\+?\s*years?\s+in\s+\w+',
    ]
    
    for pattern in patterns:
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            return int(match.group(1))
    
    return 0

def extract_education(text):
    """Extract education level from resume text"""
    education_keywords = {
        'phd': 'PhD',
        'doctorate': 'PhD', 
        'ph.d': 'PhD',
        'masters': 'Masters',
        'master': 'Masters',
        'm.tech': 'Masters',
        'mtech': 'Masters',
        'mba': 'Masters',
        'bachelor': 'Bachelors',
        'btech': 'Bachelors',
        'b.tech': 'Bachelors',
        'be': 'Bachelors',
        'b.e': 'Bachelors',
        'bsc': 'Bachelors',
        'b.sc': 'Bachelors'
    }
    
    text_lower = text.lower()
    
    for keyword, level in education_keywords.items():
        if keyword in text_lower:
            return level
    
    return 'Not specified'

def analyze_resume(pdf_path):
    """Main function to analyze resume and return structured data"""
    
    # Extract text from PDF
    text = extract_text_from_pdf(pdf_path)
    
    if text.startswith("Error"):
        return {"error": text}
    
    # Use TextBlob for basic text processing
    blob = TextBlob(text)
    
    # Extract information
    skills = extract_skills(text)
    experience = extract_experience(text)
    education = extract_education(text)
    
    # Get text statistics
    word_count = len(blob.words)
    sentence_count = len(blob.sentences)
    
    # Basic sentiment analysis (confidence level)
    sentiment = blob.sentiment
    
    return {
        "skills": skills,
        "experience_years": experience,
        "education_level": education,
        "word_count": word_count,
        "sentence_count": sentence_count,
        "confidence_score": round((sentiment.polarity + 1) * 50, 2),  # Convert to 0-100 scale
        "raw_text": text[:500] + "..." if len(text) > 500 else text  # First 500 chars for preview
    }

def main():
    """Main function for command line usage"""
    if len(sys.argv) != 2:
        print("Usage: python resume_parser.py <pdf_path>")
        sys.exit(1)
    
    pdf_path = sys.argv[1]
    result = analyze_resume(pdf_path)
    
    # Output as JSON for PHP to parse
    print(json.dumps(result, indent=2))

if __name__ == "__main__":
    main()
