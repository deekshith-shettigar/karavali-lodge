-- =============================================
-- Karavali Lodge — Reset Guest & Booking Data
-- =============================================
-- Clears all guest/booking/operational data
-- while keeping: rooms, admins, service_menu
-- Uses DELETE instead of TRUNCATE to avoid
-- foreign key constraint errors.
--
-- Run in phpMyAdmin:
--   karavali_lodge → SQL tab → paste → Go
-- =============================================

SET FOREIGN_KEY_CHECKS=0;

DELETE FROM bills;
DELETE FROM guest_services;
DELETE FROM room_service;
DELETE FROM id_proofs;
DELETE FROM night_audits;
DELETE FROM housekeeping;
DELETE FROM checkins;
DELETE FROM online_booking_requests;
DELETE FROM bookings;
DELETE FROM contact_messages;
DELETE FROM login_attempts;
DELETE FROM password_reset_otps;
DELETE FROM guests;

SET FOREIGN_KEY_CHECKS=1;

-- Reset all rooms back to Available
UPDATE rooms SET status = 'Available', updated_at = NOW();

-- Confirm
SELECT 'Karavali Lodge data reset complete. Admins, rooms and service menu are untouched.' AS status;
