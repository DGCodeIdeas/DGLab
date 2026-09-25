CREATE TABLE IF NOT EXISTS showcase_products (
    id           CHAR(26)      NOT NULL PRIMARY KEY,
    sku          VARCHAR(64)   NOT NULL UNIQUE,
    title        VARCHAR(255)  NOT NULL,
    slug         VARCHAR(255)  NOT NULL UNIQUE,
    description  TEXT          NULL,
    price_cents  BIGINT        NOT NULL,
    currency     CHAR(3)       NOT NULL DEFAULT 'USD',
    status       VARCHAR(32)   NOT NULL DEFAULT 'draft',
    created_at   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at   TIMESTAMP(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    INDEX idx_showcase_products_status_slug (status, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
