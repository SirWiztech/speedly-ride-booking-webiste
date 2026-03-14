-- =====================================================
-- SPEEDLY - Complete Database Schema (MySQL)
-- Ride Hailing Platform
-- =====================================================

-- =====================================================
-- USERS & AUTHENTICATION
-- =====================================================

-- Users table (base for all user types)
CREATE TABLE users (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    profile_picture_url TEXT,
    date_of_birth DATE,
    gender VARCHAR(20) CHECK (gender IN ('male', 'female', 'other', 'prefer-not-to-say')),
    is_active BOOLEAN DEFAULT true,
    is_verified BOOLEAN DEFAULT false,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- User roles
CREATE TABLE user_roles (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36),
    role VARCHAR(50) NOT NULL CHECK (role IN ('admin', 'client', 'driver', 'super_admin')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_role (user_id, role),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login history
CREATE TABLE login_history (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36),
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_type VARCHAR(50),
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    logout_time TIMESTAMP NULL,
    is_successful BOOLEAN DEFAULT true,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Remember me tokens
CREATE TABLE remember_tokens (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36),
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Password reset tokens
CREATE TABLE password_resets (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36),
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Session management (for admin)
CREATE TABLE admin_sessions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    admin_id CHAR(36),
    session_token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- CLIENT SPECIFIC DATA
-- =====================================================

-- Client profiles (extends users)
CREATE TABLE client_profiles (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) UNIQUE,
    membership_tier VARCHAR(50) DEFAULT 'basic' CHECK (membership_tier IN ('basic', 'premium', 'gold')),
    total_rides INT DEFAULT 0,
    total_spent DECIMAL(12,2) DEFAULT 0,
    average_rating DECIMAL(3,2),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    home_address TEXT,
    office_address TEXT,
    preferred_payment_method VARCHAR(50),
    ride_preferences JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Saved locations (for clients)
CREATE TABLE saved_locations (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36),
    location_name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    location_type VARCHAR(50) CHECK (location_type IN ('home', 'work', 'favorite', 'other')),
    is_default BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- DRIVER SPECIFIC DATA
-- =====================================================

-- Driver profiles
CREATE TABLE driver_profiles (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) UNIQUE,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    license_expiry DATE NOT NULL,
    driver_status VARCHAR(50) DEFAULT 'offline' CHECK (driver_status IN ('online', 'offline', 'on_ride', 'suspended')),
    verification_status VARCHAR(50) DEFAULT 'pending' CHECK (verification_status IN ('pending', 'approved', 'rejected', 'suspended')),
    total_earnings DECIMAL(12,2) DEFAULT 0,
    wallet_balance DECIMAL(12,2) DEFAULT 0,
    completed_rides INT DEFAULT 0,
    cancelled_rides INT DEFAULT 0,
    average_rating DECIMAL(3,2),
    total_reviews INT DEFAULT 0,
    acceptance_rate DECIMAL(5,2),
    current_latitude DECIMAL(10,8),
    current_longitude DECIMAL(11,8),
    last_location_update TIMESTAMP NULL,
    is_available BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Driver KYC documents
CREATE TABLE driver_kyc_documents (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    driver_id CHAR(36),
    document_type VARCHAR(50) NOT NULL CHECK (document_type IN ('drivers_license_front', 'drivers_license_back', 'selfie_with_id', 'insurance', 'vehicle_registration', 'road_worthiness')),
    document_url TEXT NOT NULL,
    verification_status VARCHAR(50) DEFAULT 'pending' CHECK (verification_status IN ('pending', 'approved', 'rejected')),
    rejection_reason TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    verified_by CHAR(36),
    FOREIGN KEY (driver_id) REFERENCES driver_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- Driver vehicles
CREATE TABLE driver_vehicles (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    driver_id CHAR(36),
    vehicle_model VARCHAR(100) NOT NULL,
    vehicle_year INT,
    vehicle_color VARCHAR(50),
    plate_number VARCHAR(20) UNIQUE NOT NULL,
    vehicle_type VARCHAR(50) DEFAULT 'sedan' CHECK (vehicle_type IN ('sedan', 'suv', 'hatchback', 'minivan', 'luxury', 'economy')),
    passenger_capacity INT DEFAULT 4,
    insurance_expiry DATE,
    road_worthiness_expiry DATE,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES driver_profiles(id) ON DELETE CASCADE
);

-- Driver earnings breakdown
CREATE TABLE driver_earnings (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    driver_id CHAR(36),
    ride_id CHAR(36),
    amount DECIMAL(10,2) NOT NULL,
    commission DECIMAL(10,2) NOT NULL,
    net_earnings DECIMAL(10,2) NOT NULL,
    earnings_type VARCHAR(50) CHECK (earnings_type IN ('ride', 'bonus', 'referral', 'adjustment')),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES driver_profiles(id) ON DELETE CASCADE
);

-- Driver withdrawals
CREATE TABLE driver_withdrawals (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    driver_id CHAR(36),
    amount DECIMAL(10,2) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(20) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'paid')),
    processed_by CHAR(36),
    processed_at TIMESTAMP NULL,
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES driver_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Driver availability schedule
CREATE TABLE driver_schedule (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    driver_id CHAR(36),
    day_of_week INT CHECK (day_of_week BETWEEN 0 AND 6),
    start_time TIME,
    end_time TIME,
    is_active BOOLEAN DEFAULT true,
    FOREIGN KEY (driver_id) REFERENCES driver_profiles(id) ON DELETE CASCADE
);

-- =====================================================
-- RIDES MANAGEMENT
-- =====================================================

-- Rides table
CREATE TABLE rides (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    ride_number VARCHAR(20) UNIQUE NOT NULL,
    client_id CHAR(36),
    driver_id CHAR(36),
    
    -- Locations
    pickup_address TEXT NOT NULL,
    pickup_latitude DECIMAL(10,8) NOT NULL,
    pickup_longitude DECIMAL(11,8) NOT NULL,
    destination_address TEXT NOT NULL,
    destination_latitude DECIMAL(10,8) NOT NULL,
    destination_longitude DECIMAL(11,8) NOT NULL,
    
    -- Ride details
    ride_type VARCHAR(50) DEFAULT 'economy' CHECK (ride_type IN ('economy', 'comfort', 'premium', 'family', 'courier', 'hauling')),
    distance_km DECIMAL(6,2),
    duration_minutes INT,
    
    -- Pricing
    base_fare DECIMAL(10,2),
    distance_fare DECIMAL(10,2),
    time_fare DECIMAL(10,2),
    surge_multiplier DECIMAL(3,2) DEFAULT 1.0,
    service_fee DECIMAL(10,2),
    tax DECIMAL(10,2),
    total_fare DECIMAL(10,2) NOT NULL,
    platform_commission DECIMAL(10,2),
    driver_payout DECIMAL(10,2),
    
    -- Status
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'driver_assigned', 'driver_arrived', 'ongoing', 'completed', 'cancelled_by_client', 'cancelled_by_driver', 'cancelled_by_admin')),
    payment_status VARCHAR(50) DEFAULT 'pending' CHECK (payment_status IN ('pending', 'paid', 'failed', 'refunded')),
    
    -- Tracking
    actual_pickup_time TIMESTAMP NULL,
    actual_dropoff_time TIMESTAMP NULL,
    scheduled_time TIMESTAMP NULL,
    
    -- Ratings
    client_rating INT CHECK (client_rating BETWEEN 1 AND 5),
    driver_rating INT CHECK (driver_rating BETWEEN 1 AND 5),
    client_review TEXT,
    driver_review TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES client_profiles(id) ON DELETE SET NULL,
    FOREIGN KEY (driver_id) REFERENCES driver_profiles(id) ON DELETE SET NULL
);

-- Ride tracking (real-time location updates)
CREATE TABLE ride_tracking (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    ride_id CHAR(36),
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    speed_kmh DECIMAL(5,2),
    heading INT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE
);

-- Ride cancellation reasons
CREATE TABLE ride_cancellations (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    ride_id CHAR(36) UNIQUE,
    cancelled_by CHAR(36),
    reason VARCHAR(255) NOT NULL,
    detailed_reason TEXT,
    cancelled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE CASCADE,
    FOREIGN KEY (cancelled_by) REFERENCES users(id)
);

-- =====================================================
-- PAYMENTS & WALLETS
-- =====================================================

-- Payment methods
CREATE TABLE payment_methods (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36),
    method_type VARCHAR(50) NOT NULL CHECK (method_type IN ('card', 'bank_transfer', 'wallet', 'cash', 'paypal')),
    provider VARCHAR(50),
    account_last4 VARCHAR(4),
    card_expiry DATE,
    is_default BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Wallet transactions
CREATE TABLE wallet_transactions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36),
    transaction_type VARCHAR(50) NOT NULL CHECK (transaction_type IN ('deposit', 'withdrawal', 'ride_payment', 'ride_refund', 'bonus', 'referral')),
    amount DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    reference VARCHAR(100) UNIQUE,
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'failed', 'cancelled')),
    description TEXT,
    ride_id CHAR(36),
    payment_method_id CHAR(36),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
);

-- Payment transactions (from payment gateway)
CREATE TABLE payment_transactions (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    transaction_reference VARCHAR(100) UNIQUE NOT NULL,
    user_id CHAR(36),
    ride_id CHAR(36),
    amount DECIMAL(10,2) NOT NULL,
    commission DECIMAL(10,2),
    currency VARCHAR(3) DEFAULT 'NGN',
    payment_method VARCHAR(50),
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'success', 'failed', 'refunded')),
    gateway_response JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE SET NULL
);

-- =====================================================
-- DISPUTES & SUPPORT
-- =====================================================

-- Disputes
CREATE TABLE disputes (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    dispute_number VARCHAR(20) UNIQUE NOT NULL,
    ride_id CHAR(36),
    raised_by CHAR(36),
    raised_against CHAR(36),
    dispute_type VARCHAR(50) CHECK (dispute_type IN ('ride_issue', 'payment_issue', 'driver_behavior', 'client_behavior', 'other')),
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'open' CHECK (status IN ('open', 'investigating', 'resolved', 'closed')),
    priority VARCHAR(20) DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    assigned_to CHAR(36),
    resolution TEXT,
    resolved_at TIMESTAMP NULL,
    resolved_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE SET NULL,
    FOREIGN KEY (raised_by) REFERENCES users(id),
    FOREIGN KEY (raised_against) REFERENCES users(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);

-- Dispute messages
CREATE TABLE dispute_messages (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    dispute_id CHAR(36),
    sender_id CHAR(36),
    message TEXT NOT NULL,
    attachment_url TEXT,
    is_admin_reply BOOLEAN DEFAULT false,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispute_id) REFERENCES disputes(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id)
);

-- Support tickets
CREATE TABLE support_tickets (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    user_id CHAR(36),
    subject VARCHAR(255) NOT NULL,
    category VARCHAR(50) CHECK (category IN ('account', 'ride', 'payment', 'technical', 'feedback', 'other')),
    priority VARCHAR(20) DEFAULT 'normal',
    status VARCHAR(50) DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'resolved', 'closed')),
    assigned_to CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- =====================================================
-- ADMIN DASHBOARD DATA
-- =====================================================

-- System settings
CREATE TABLE system_settings (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'string',
    description TEXT,
    updated_by CHAR(36),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Insert default settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
    ('base_fare', '500', 'integer', 'Base fare in Naira'),
    ('rate_per_km', '150', 'integer', 'Rate per kilometer in Naira'),
    ('surge_multiplier', '1.5', 'decimal', 'Surge pricing multiplier'),
    ('platform_commission', '20', 'integer', 'Platform commission percentage'),
    ('currency_symbol', '₦', 'string', 'Currency symbol'),
    ('currency_code', 'NGN', 'string', 'Currency code'),
    ('enable_surge_pricing', 'true', 'boolean', 'Enable surge pricing'),
    ('require_driver_approval', 'true', 'boolean', 'Require admin approval for drivers'),
    ('maintenance_mode', 'false', 'boolean', 'Maintenance mode status'),
    ('session_timeout', '30', 'integer', 'Session timeout in minutes');

-- Admin activity logs
CREATE TABLE admin_activity_logs (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    admin_id CHAR(36),
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50),
    entity_id CHAR(36),
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Driver approval queue
CREATE TABLE driver_approval_queue (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    driver_id CHAR(36),
    reviewed_by CHAR(36),
    review_notes TEXT,
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES driver_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- Promo codes
CREATE TABLE promo_codes (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    discount_type VARCHAR(20) CHECK (discount_type IN ('percentage', 'fixed')),
    discount_value DECIMAL(10,2) NOT NULL,
    max_discount DECIMAL(10,2),
    min_ride_value DECIMAL(10,2),
    usage_limit INT,
    usage_count INT DEFAULT 0,
    valid_from TIMESTAMP NULL,
    valid_until TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT true,
    created_by CHAR(36),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- User promo usage
CREATE TABLE promo_usage (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    promo_id CHAR(36),
    user_id CHAR(36),
    ride_id CHAR(36),
    discount_amount DECIMAL(10,2),
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_promo_user_ride (promo_id, user_id, ride_id),
    FOREIGN KEY (promo_id) REFERENCES promo_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE SET NULL
);

-- =====================================================
-- NOTIFICATIONS
-- =====================================================

-- Notifications
CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36),
    type VARCHAR(50) NOT NULL CHECK (type IN ('ride_update', 'payment', 'promotion', 'system', 'dispute', 'reminder')),
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT false,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Push notification tokens
CREATE TABLE push_tokens (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36),
    device_type VARCHAR(20) CHECK (device_type IN ('ios', 'android', 'web')),
    token VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_token (user_id, token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- =====================================================
-- REPORTS & ANALYTICS (Aggregated tables)
-- =====================================================

-- Daily revenue summary
CREATE TABLE daily_revenue_summary (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    date DATE UNIQUE NOT NULL,
    total_rides INT DEFAULT 0,
    completed_rides INT DEFAULT 0,
    cancelled_rides INT DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0,
    platform_commission DECIMAL(12,2) DEFAULT 0,
    driver_payouts DECIMAL(12,2) DEFAULT 0,
    average_fare DECIMAL(10,2),
    new_users INT DEFAULT 0,
    new_drivers INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Driver performance summary
CREATE TABLE driver_performance_summary (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    driver_id CHAR(36),
    date DATE NOT NULL,
    rides_completed INT DEFAULT 0,
    rides_cancelled INT DEFAULT 0,
    total_earnings DECIMAL(10,2) DEFAULT 0,
    average_rating DECIMAL(3,2),
    online_minutes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_driver_date (driver_id, date),
    FOREIGN KEY (driver_id) REFERENCES driver_profiles(id) ON DELETE CASCADE
);

-- =====================================================
-- INDEXES FOR PERFORMANCE
-- =====================================================

-- Users indexes
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_phone ON users(phone_number);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_created_at ON users(created_at);

-- Rides indexes
CREATE INDEX idx_rides_client_id ON rides(client_id);
CREATE INDEX idx_rides_driver_id ON rides(driver_id);
CREATE INDEX idx_rides_status ON rides(status);
CREATE INDEX idx_rides_created_at ON rides(created_at);
CREATE INDEX idx_rides_pickup_location ON rides(pickup_latitude, pickup_longitude);
CREATE INDEX idx_rides_destination ON rides(destination_latitude, destination_longitude);

-- Driver indexes
CREATE INDEX idx_driver_profiles_status ON driver_profiles(driver_status);
CREATE INDEX idx_driver_profiles_verification ON driver_profiles(verification_status);
CREATE INDEX idx_driver_profiles_location ON driver_profiles(current_latitude, current_longitude) WHERE is_available = true;

-- Transaction indexes
CREATE INDEX idx_wallet_transactions_user_id ON wallet_transactions(user_id);
CREATE INDEX idx_wallet_transactions_created_at ON wallet_transactions(created_at);
CREATE INDEX idx_payment_transactions_reference ON payment_transactions(transaction_reference);
CREATE INDEX idx_payment_transactions_user_id ON payment_transactions(user_id);

-- Notification indexes
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_is_read ON notifications(is_read) WHERE is_read = false;

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- Active drivers view
CREATE VIEW active_drivers AS
SELECT 
    dp.*,
    u.full_name,
    u.phone_number,
    dv.vehicle_model,
    dv.plate_number,
    dv.vehicle_color
FROM driver_profiles dp
JOIN users u ON u.id = dp.user_id
LEFT JOIN driver_vehicles dv ON dv.driver_id = dp.id
WHERE dp.driver_status = 'online' 
  AND dp.is_available = true 
  AND dp.verification_status = 'approved';

-- Ride details view
CREATE VIEW ride_details AS
SELECT 
    r.*,
    u_client.full_name AS client_name,
    u_client.phone_number AS client_phone,
    u_driver.full_name AS driver_name,
    u_driver.phone_number AS driver_phone,
    dv.vehicle_model,
    dv.plate_number
FROM rides r
LEFT JOIN client_profiles cp ON cp.id = r.client_id
LEFT JOIN users u_client ON u_client.id = cp.user_id
LEFT JOIN driver_profiles dp ON dp.id = r.driver_id
LEFT JOIN users u_driver ON u_driver.id = dp.user_id
LEFT JOIN driver_vehicles dv ON dv.driver_id = dp.id;

-- Admin dashboard summary view
CREATE VIEW admin_dashboard_summary AS
SELECT
    (SELECT COUNT(*) FROM users WHERE is_active = true AND deleted_at IS NULL) AS total_users,
    (SELECT COUNT(*) FROM driver_profiles WHERE verification_status = 'approved') AS total_drivers,
    (SELECT COUNT(*) FROM rides WHERE status IN ('ongoing', 'driver_assigned', 'driver_arrived')) AS active_rides,
    (SELECT COUNT(*) FROM rides WHERE status = 'completed') AS completed_rides,
    (SELECT COALESCE(SUM(total_fare), 0) FROM rides WHERE status = 'completed') AS total_revenue,
    (SELECT COALESCE(SUM(amount), 0) FROM driver_withdrawals WHERE status = 'pending') AS pending_withdrawals,
    (SELECT COUNT(*) FROM driver_profiles WHERE verification_status = 'pending') AS pending_driver_approvals,
    (SELECT COUNT(*) FROM disputes WHERE status = 'open') AS open_disputes;

-- =====================================================
-- TRIGGERS AND FUNCTIONS
-- =====================================================

-- Generate ride number trigger
DELIMITER $$

CREATE TRIGGER generate_ride_number_trigger
BEFORE INSERT ON rides
FOR EACH ROW
BEGIN
    DECLARE year_prefix VARCHAR(4);
    DECLARE sequence_num INT;
    DECLARE new_ride_number VARCHAR(20);
    
    SET year_prefix = DATE_FORMAT(NEW.created_at, '%Y');
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(ride_number, 6) AS UNSIGNED)), 0) + 1
    INTO sequence_num
    FROM rides
    WHERE ride_number LIKE CONCAT('R', year_prefix, '%');
    
    SET new_ride_number = CONCAT('R', year_prefix, LPAD(sequence_num, 5, '0'));
    SET NEW.ride_number = new_ride_number;
END$$

-- Update driver earnings after ride completion
CREATE TRIGGER update_driver_earnings_trigger
AFTER UPDATE ON rides
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND (OLD.status != 'completed' OR OLD.status IS NULL) THEN
        UPDATE driver_profiles
        SET 
            completed_rides = completed_rides + 1,
            total_earnings = total_earnings + NEW.driver_payout,
            wallet_balance = wallet_balance + NEW.driver_payout
        WHERE id = NEW.driver_id;
        
        INSERT INTO driver_earnings (driver_id, ride_id, amount, commission, net_earnings, earnings_type)
        VALUES (NEW.driver_id, NEW.id, NEW.total_fare, NEW.platform_commission, NEW.driver_payout, 'ride');
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- INITIAL DATA (for testing/demo)
-- =====================================================

-- Insert admin user (password: Admin@123 - should be hashed in real app)
INSERT INTO users (id, username, email, password_hash, phone_number, full_name, is_verified, is_active)
VALUES 
    (UUID(), 'superadmin', 'admin@speedly.com', '$2a$10$YourHashedPasswordHere', '+2348000000000', 'Super Admin', true, true);

-- Insert sample settings
INSERT INTO system_settings (setting_key, setting_value) VALUES
    ('app_version', '2.5.1'),
    ('min_withdrawal', '1000'),
    ('max_withdrawal', '500000'),
    ('referral_bonus', '500'),
    ('welcome_bonus', '1000');

    ALTER TABLE users 
ADD COLUMN reset_token VARCHAR(255) NULL AFTER password,
ADD COLUMN reset_expires DATETIME NULL AFTER reset_token;

-- Insert promo codes
INSERT INTO promo_codes (code, description, discount_type, discount_value, valid_from, valid_until) VALUES
    ('WELCOME10', '10% off your first ride', 'percentage', 10, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
    ('FLAT500', '₦500 off on rides above ₦3000', 'fixed', 500, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY));

COMMIT;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id)
);

ALTER TABLE users 
ADD COLUMN reset_token VARCHAR(255) NULL AFTER password,
ADD COLUMN reset_expires DATETIME NULL AFTER reset_token;