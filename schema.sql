-- ============================================
-- Схема базы данных системы «ProjRequest»
-- СУБД: PostgreSQL
-- ============================================

-- Таблица пользователей системы
CREATE TABLE users (
    id              SERIAL PRIMARY KEY,
    full_name       VARCHAR(255) NOT NULL,
    login           VARCHAR(100) UNIQUE NOT NULL,       -- Email используется как логин
    password_hash   VARCHAR(255) NOT NULL,
    organization    VARCHAR(255),                       -- Организация (для заказчика)
    role            VARCHAR(20) NOT NULL DEFAULT 'client'
                    CHECK (role IN ('client', 'moderator', 'admin')),
    phone           VARCHAR(20),
    registered_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    last_login      TIMESTAMP,
    failed_attempts INT NOT NULL DEFAULT 0,             -- Счётчик неудачных попыток входа
    locked_until    TIMESTAMP                           -- Время блокировки аккаунта
);

-- Таблица заявок
CREATE TABLE applications (
    id              SERIAL PRIMARY KEY,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    project_name    VARCHAR(255) NOT NULL,
    description     TEXT NOT NULL,
    tasks           TEXT,                               -- Задачи проекта
    budget          DECIMAL(15,2) NOT NULL DEFAULT 0.00
                    CHECK (budget >= 0),
    required_resources TEXT,                            -- Требуемые ресурсы
    status          VARCHAR(20) NOT NULL DEFAULT 'draft'
                    CHECK (status IN ('draft', 'pending', 'approved', 'rejected', 'revision', 'completed')),
    moderator_id    INT REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Таблица деталей технического задания (вынесена отдельно для безопасности)
CREATE TABLE task_details (
    id                      SERIAL PRIMARY KEY,
    application_id          INT NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    file_path               TEXT,                       -- Ссылка на загруженный файл
    technical_parameters    JSONB,                      -- Технические параметры в JSON
    additional_requirements TEXT                        -- Доп. требования (шифрованное поле)
);

-- Таблица истории изменения статусов (только добавление, без редактирования)
CREATE TABLE status_history (
    id              SERIAL PRIMARY KEY,
    application_id  INT NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    old_status      VARCHAR(20) NOT NULL,
    new_status      VARCHAR(20) NOT NULL,
    changed_by      INT NOT NULL REFERENCES users(id),
    changed_at      TIMESTAMP NOT NULL DEFAULT NOW(),
    comment         TEXT
);

-- Таблица чата и комментариев к заявкам
CREATE TABLE chat_messages (
    id              SERIAL PRIMARY KEY,
    application_id  INT NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    author_id       INT NOT NULL REFERENCES users(id),
    message         TEXT NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Индексы для ускорения выборок
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_applications_user_id ON applications(user_id);
CREATE INDEX idx_status_history_application ON status_history(application_id);
CREATE INDEX idx_chat_messages_application ON chat_messages(application_id);
CREATE INDEX idx_users_login ON users(login);