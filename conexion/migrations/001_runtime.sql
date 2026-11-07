CREATE TABLE IF NOT EXISTS zentra_sessions (
  sid varchar(128) NOT NULL PRIMARY KEY,
  data mediumtext NOT NULL,
  expires_at datetime NOT NULL,
  KEY idx_sessions_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS zentra_webhook_events (
  event_id varchar(191) NOT NULL PRIMARY KEY,
  event_type varchar(120) NOT NULL,
  processed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
