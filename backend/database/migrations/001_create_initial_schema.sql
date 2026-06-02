CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    surname VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('amministratore','responsabile_ufficio','operatore','lettore') NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE login_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    username_attempted VARCHAR(190) NOT NULL,
    success BOOLEAN NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_values_hash CHAR(64) NULL,
    new_values_hash CHAR(64) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL UNIQUE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE agents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    surname VARCHAR(100) NOT NULL,
    rank VARCHAR(100) NULL,
    office VARCHAR(150) NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE activity_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL UNIQUE,
    description TEXT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
);

CREATE TABLE controls (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registry_number INT UNSIGNED NOT NULL,
    registry_year SMALLINT UNSIGNED NOT NULL,
    control_date DATE NOT NULL,
    control_time TIME NOT NULL,
    has_event BOOLEAN NOT NULL DEFAULT FALSE,
    event_id BIGINT UNSIGNED NULL,
    business_name VARCHAR(190) NOT NULL,
    business_location VARCHAR(255) NOT NULL,
    business_owner_encrypted TEXT NULL,
    offender_encrypted TEXT NULL,
    outcome ENUM('positivo','negativo','in_accertamento') NOT NULL,
    violated_rules TEXT NULL,
    sanctioning_rules TEXT NULL,
    reduced_payment_amount DECIMAL(12,2) NULL,
    minimum_amount DECIMAL(12,2) NULL,
    maximum_amount DECIMAL(12,2) NULL,
    total_sanction_amount DECIMAL(12,2) NULL,
    alleged_crime TEXT NULL,
    cnr_number_encrypted TEXT NULL,
    administrative_seizure BOOLEAN NOT NULL DEFAULT FALSE,
    administrative_seizure_description TEXT NULL,
    criminal_seizure BOOLEAN NOT NULL DEFAULT FALSE,
    criminal_seizure_description TEXT NULL,
    weapon_precautionary_withdrawal BOOLEAN NOT NULL DEFAULT FALSE,
    weapon_precautionary_withdrawal_description TEXT NULL,
    notes_encrypted TEXT NULL,
    status ENUM('bozza','validato','annullato') NOT NULL DEFAULT 'bozza',
    hash_record CHAR(64) NOT NULL,
    previous_hash CHAR(64) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    validated_by BIGINT UNSIGNED NULL,
    validated_at DATETIME NULL,
    annulled_by BIGINT UNSIGNED NULL,
    annulled_at DATETIME NULL,
    annulment_reason TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY controls_registry_unique (registry_number, registry_year),
    FOREIGN KEY (event_id) REFERENCES events(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    FOREIGN KEY (validated_by) REFERENCES users(id),
    FOREIGN KEY (annulled_by) REFERENCES users(id)
);

CREATE TABLE control_activity_category (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    control_id BIGINT UNSIGNED NOT NULL,
    activity_category_id BIGINT UNSIGNED NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (control_id) REFERENCES controls(id),
    FOREIGN KEY (activity_category_id) REFERENCES activity_categories(id)
);

CREATE TABLE agent_control (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    control_id BIGINT UNSIGNED NOT NULL,
    agent_id BIGINT UNSIGNED NOT NULL,
    role_in_control VARCHAR(100) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (control_id) REFERENCES controls(id),
    FOREIGN KEY (agent_id) REFERENCES agents(id)
);

CREATE TABLE control_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    control_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    data_json JSON NOT NULL,
    hash_version CHAR(64) NOT NULL,
    previous_hash CHAR(64) NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    change_reason TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY control_versions_unique (control_id, version_number),
    FOREIGN KEY (control_id) REFERENCES controls(id),
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

CREATE TABLE exports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    export_type VARCHAR(80) NOT NULL,
    filters_json JSON NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE backups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
