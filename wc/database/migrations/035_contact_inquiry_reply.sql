-- Reply message stored when an inquiry is marked replied
SET NAMES utf8mb4;
SET time_zone = '+00:00';

ALTER TABLE contact_inquiries
    ADD COLUMN reply_message TEXT NULL AFTER status,
    ADD COLUMN replied_at DATETIME NULL AFTER reply_message;
