-- Add profile picture column to users table
ALTER TABLE users 
ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER email;

-- Add index for better performance when querying by profile picture
CREATE INDEX idx_users_profile_picture ON users(profile_picture);

-- Update existing records to have NULL profile picture (already default)
-- This migration is safe to run multiple times
