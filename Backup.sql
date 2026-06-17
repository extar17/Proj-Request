CREATE TABLE users (
    id              SERIAL PRIMARY KEY,
    full_name       VARCHAR(255) NOT NULL,
    login           VARCHAR(100) UNIQUE NOT NULL,      
    password_hash   VARCHAR(255) NOT NULL,
    organization    VARCHAR(255),                    
    role            VARCHAR(20) NOT NULL DEFAULT 'client'
                    CHECK (role IN ('client', 'moderator', 'admin')),
    phone           VARCHAR(20),
    registered_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    last_login      TIMESTAMP,
    failed_attempts INT NOT NULL DEFAULT 0,         
    locked_until    TIMESTAMP                      
);


CREATE TABLE applications (
    id              SERIAL PRIMARY KEY,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    project_name    VARCHAR(255) NOT NULL,
    description     TEXT NOT NULL,
    tasks           TEXT,                          
    budget          DECIMAL(15,2) NOT NULL DEFAULT 0.00
                    CHECK (budget >= 0),
    required_resources TEXT,                         
    status          VARCHAR(20) NOT NULL DEFAULT 'draft'
                    CHECK (status IN ('draft', 'pending', 'approved', 'rejected', 'revision', 'completed')),
    moderator_id    INT REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE task_details (
    id                      SERIAL PRIMARY KEY,
    application_id          INT NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    file_path               TEXT,                  
    technical_parameters    JSONB,                 
    additional_requirements TEXT                    
);

CREATE TABLE status_history (
    id              SERIAL PRIMARY KEY,
    application_id  INT NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    old_status      VARCHAR(20) NOT NULL,
    new_status      VARCHAR(20) NOT NULL,
    changed_by      INT NOT NULL REFERENCES users(id),
    changed_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    comment         TEXT
);

CREATE TABLE chat_messages (
    id              SERIAL PRIMARY KEY,
    application_id  INT NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    author_id       INT NOT NULL REFERENCES users(id),
    message         TEXT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_applications_user_id ON applications(user_id);
CREATE INDEX idx_status_history_application ON status_history(application_id);
CREATE INDEX idx_chat_messages_application ON chat_messages(application_id);
CREATE INDEX idx_users_login ON users(login);

ALTER TABLE applications
    -- Для модератора
    ADD COLUMN moderator_comment TEXT,       
    ADD COLUMN executor VARCHAR(255),         
    ADD COLUMN executor_id INT REFERENCES users(id) ON DELETE SET NULL, 
    ADD COLUMN deadline DATE,                  
    ADD COLUMN progress INT DEFAULT 0 CHECK (progress BETWEEN 0 AND 100), 

    ADD COLUMN completed_at TIMESTAMP,     
    ADD COLUMN rating DECIMAL(2,1) CHECK (rating BETWEEN 0 AND 5),
    ADD COLUMN review TEXT;   

CREATE TABLE tags (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE application_tags (
    application_id INT NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    tag_id INT NOT NULL REFERENCES tags(id) ON DELETE CASCADE,
    PRIMARY KEY (application_id, tag_id)
);

CREATE TABLE application_secure_data (
    id SERIAL PRIMARY KEY,
    application_id INT NOT NULL REFERENCES applications(id) ON DELETE CASCADE UNIQUE,
    encrypted_additional_req TEXT,  
    encrypted_tech_params TEXT,     
    encryption_iv TEXT,          
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE application_files (
    id SERIAL PRIMARY KEY,
    application_id INT NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id),
    file_name VARCHAR(255) NOT NULL, 
    file_path TEXT NOT NULL,     
    file_size INT,         
    file_type VARCHAR(100),  
    file_category VARCHAR(50) NOT NULL DEFAULT 'tz'
        CHECK (file_category IN ('tz', 'report', 'other')),
    uploaded_at TIMESTAMP NOT NULL DEFAULT NOW()
);
ALTER TABLE status_history ADD COLUMN IF NOT EXISTS comment TEXT;

CREATE TABLE notifications (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    application_id INT REFERENCES applications(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL
        CHECK (type IN ('status_change', 'new_comment', 'revision_needed', 'application_approved', 'application_rejected')),
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_applications_status_created ON applications(status, created_at DESC);
CREATE INDEX idx_applications_executor ON applications(executor_id);
CREATE INDEX idx_applications_deadline ON applications(deadline);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_files_application ON application_files(application_id);
CREATE INDEX idx_secure_data_application ON application_secure_data(application_id);
