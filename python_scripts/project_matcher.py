#!/usr/bin/env python3
# project_matcher.py - Match student skills with project requirements

import sys
import json
from textblob import TextBlob

def calculate_skill_match(student_skills, project_skills):
    """Calculate percentage match between student skills and project requirements"""
    
    if not student_skills or not project_skills:
        return 0.0
    
    # Convert to lowercase for comparison
    student_skills_lower = [skill.lower() for skill in student_skills]
    project_skills_lower = [skill.strip().lower() for skill in project_skills.split(',')]
    
    # Find matching skills
    matches = []
    for project_skill in project_skills_lower:
        for student_skill in student_skills_lower:
            if project_skill in student_skill or student_skill in project_skill:
                matches.append(project_skill)
                break
    
    # Calculate match percentage
    if len(project_skills_lower) == 0:
        return 0.0
    
    match_percentage = (len(matches) / len(project_skills_lower)) * 100
    return round(match_percentage, 2)

def analyze_project_text(project_description):
    """Analyze project description using TextBlob"""
    
    blob = TextBlob(project_description)
    
    # Extract noun phrases (potential skills/technologies)
    noun_phrases = list(blob.noun_phrases)
    
    # Get sentiment (project attractiveness)
    sentiment = blob.sentiment
    
    return {
        "noun_phrases": noun_phrases,
        "sentiment_score": round(sentiment.polarity, 2),
        "subjectivity": round(sentiment.subjectivity, 2),
        "word_count": len(blob.words)
    }

def match_student_to_project(student_data, project_data):
    """Match a student to a project based on multiple factors"""
    
    # Calculate skill match
    skill_match = calculate_skill_match(
        student_data.get('skills', []),
        project_data.get('required_skills', '')
    )
    
    # Analyze project text
    project_analysis = analyze_project_text(project_data.get('description', ''))
    
    # Calculate experience factor (bonus for experience)
    experience_bonus = min(student_data.get('experience_years', 0) * 5, 20)  # Max 20% bonus
    
    # Calculate education factor
    education_levels = {'PhD': 20, 'Masters': 15, 'Bachelors': 10, 'Not specified': 0}
    education_bonus = education_levels.get(student_data.get('education_level', 'Not specified'), 0)
    
    # Final match score (weighted)
    final_score = (
        skill_match * 0.6 +           # 60% weight on skills
        experience_bonus * 0.2 +      # 20% weight on experience
        education_bonus * 0.2         # 20% weight on education
    )
    
    return {
        "match_percentage": round(min(final_score, 100), 2),  # Cap at 100%
        "skill_match": skill_match,
        "experience_bonus": experience_bonus,
        "education_bonus": education_bonus,
        "project_analysis": project_analysis,
        "matching_skills": [
            skill for skill in student_data.get('skills', [])
            if any(skill.lower() in req_skill.lower() 
                  for req_skill in project_data.get('required_skills', '').split(','))
        ]
    }

def main():
    """Main function for command line usage"""
    if len(sys.argv) != 3:
        print("Usage: python project_matcher.py <student_json> <project_json>")
        sys.exit(1)
    
    try:
        student_data = json.loads(sys.argv[1])
        project_data = json.loads(sys.argv[2])
        
        result = match_student_to_project(student_data, project_data)
        print(json.dumps(result, indent=2))
        
    except json.JSONDecodeError as e:
        print(json.dumps({"error": f"JSON decode error: {str(e)}"}))
    except Exception as e:
        print(json.dumps({"error": f"Processing error: {str(e)}"}))

if __name__ == "__main__":
    main()
