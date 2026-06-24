CREATE TABLE IF NOT EXISTS share_targets (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `key`        VARCHAR(32)   NOT NULL,
  label        VARCHAR(40)   NOT NULL,
  icon         VARCHAR(32)   NOT NULL,
  url_template VARCHAR(512)  NOT NULL,
  sort         INT           NOT NULL DEFAULT 0,
  enabled      TINYINT(1)    NOT NULL DEFAULT 1,
  UNIQUE KEY uq_share_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO share_targets (`key`, label, icon, url_template, sort, enabled) VALUES
('whatsapp','WhatsApp','ic-whatsapp','https://wa.me/?text={url}',10,1),
('telegram','Telegram','ic-telegram','https://t.me/share/url?url={url}',20,1),
('messenger','Messenger','ic-messenger','https://www.facebook.com/dialog/send?link={url}&redirect_uri={url}',30,1),
('line','Line','ic-line','https://social-plugins.line.me/lineit/share?url={url}',40,1),
('sms','SMS','ic-sms','sms:?body={url}',50,1),
('x','X','ic-x','https://twitter.com/intent/tweet?text={url}',60,1)
ON DUPLICATE KEY UPDATE label=VALUES(label), icon=VALUES(icon), url_template=VALUES(url_template), sort=VALUES(sort);
