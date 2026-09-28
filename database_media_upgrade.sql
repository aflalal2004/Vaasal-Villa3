-- =====================================================================
--  Vaasal Villa IMS — Website media upgrade (photos, gallery, video)
--  For databases imported BEFORE the media update. Safe to run twice.
--  (A fresh import of database.sql already includes all of this.)
-- =====================================================================
USE vaasal_villa_ims3;

ALTER TABLE room_types ADD COLUMN IF NOT EXISTS image_url VARCHAR(500) NULL AFTER amenities;

CREATE TABLE IF NOT EXISTS gallery_images (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  image_url    VARCHAR(500) NOT NULL,
  caption      VARCHAR(160) NULL,
  category     ENUM('villa','rooms','dining','spa','experiences') NOT NULL DEFAULT 'villa',
  room_type_id INT UNSIGNED NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gal_rtype FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO settings (skey, svalue) VALUES
('hero_video_url',  'https://videos.pexels.com/video-files/1093662/1093662-hd_1280_720_30fps.mp4'),
('hero_video_mobile_url', 'https://videos.pexels.com/video-files/1093662/1093662-sd_640_360_30fps.mp4'),
('hero_poster_url', 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1920&q=80'),
('promo_video_url', 'https://videos.pexels.com/video-files/3571264/3571264-sd_960_540_30fps.mp4');

UPDATE room_types SET image_url = 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?auto=format&fit=crop&w=1200&q=80' WHERE slug = 'garden-deluxe'      AND image_url IS NULL;
UPDATE room_types SET image_url = 'https://images.unsplash.com/photo-1602002418082-a4443e081dd1?auto=format&fit=crop&w=1200&q=80' WHERE slug = 'ocean-view-suite'   AND image_url IS NULL;
UPDATE room_types SET image_url = 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1200&q=80' WHERE slug = 'family-villa'       AND image_url IS NULL;
UPDATE room_types SET image_url = 'https://images.unsplash.com/photo-1613977257363-707ba9348227?auto=format&fit=crop&w=1200&q=80' WHERE slug = 'private-pool-villa' AND image_url IS NULL;

INSERT INTO gallery_images (image_url, caption, category, room_type_id, sort_order)
SELECT * FROM (
  SELECT 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1600&q=80' u, 'Main pool & lounge pavilion' c, 'villa' cat, NULL rt, 1 s UNION ALL
  SELECT 'https://images.unsplash.com/photo-1582719508461-905c673771fd?auto=format&fit=crop&w=1600&q=80', 'Infinity pool at sunset', 'villa', NULL, 2 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1540541338287-41700207dee6?auto=format&fit=crop&w=1600&q=80', 'The villa from above', 'villa', NULL, 3 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=1600&q=80', 'Evenings by the pool', 'villa', NULL, 4 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=1600&q=80', 'Our private beach', 'villa', NULL, 5 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=1600&q=80', 'Garden Deluxe — bright and airy', 'rooms', 1, 10 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1584132967334-10e028bd69f7?auto=format&fit=crop&w=1600&q=80', 'Garden terrace', 'rooms', 1, 11 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?auto=format&fit=crop&w=1600&q=80', 'Ocean View Suite bedroom', 'rooms', 2, 12 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1582719508461-905c673771fd?auto=format&fit=crop&w=1600&q=80', 'Suite balcony view', 'rooms', 2, 13 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1600&q=80', 'Family Villa exterior', 'rooms', 3, 14 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1600&q=80', 'Family Villa living area', 'rooms', 3, 15 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?auto=format&fit=crop&w=1600&q=80', 'Private plunge pool', 'rooms', 4, 16 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1573843981267-be1999ff37cd?auto=format&fit=crop&w=1600&q=80', 'Pool villa lagoon views', 'rooms', 4, 17 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=1600&q=80', 'Seaside terrace restaurant', 'dining', NULL, 20 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1600&q=80', 'Chef''s tasting plates', 'dining', NULL, 21 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1600&q=80', 'The Vaasal Kitchen dining room', 'dining', NULL, 22 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=1600&q=80', 'Ayurvedic massage', 'spa', NULL, 30 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=1600&q=80', 'Spa rituals', 'spa', NULL, 31 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?auto=format&fit=crop&w=1600&q=80', 'Temple day tours', 'experiences', NULL, 40 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1506929562872-bb421503ef21?auto=format&fit=crop&w=1600&q=80', 'Boat trips & snorkelling', 'experiences', NULL, 41 UNION ALL
  SELECT 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1600&q=80', 'Sunset on the bay', 'experiences', NULL, 42
) seed
WHERE NOT EXISTS (SELECT 1 FROM gallery_images);

-- Switch the earlier demo videos (if unchanged) to lighter renditions for faster loading
UPDATE settings SET svalue = 'https://videos.pexels.com/video-files/1093662/1093662-hd_1280_720_30fps.mp4' WHERE skey = 'hero_video_url'  AND svalue = 'https://videos.pexels.com/video-files/1093662/1093662-hd_1920_1080_30fps.mp4';
UPDATE settings SET svalue = 'https://videos.pexels.com/video-files/3571264/3571264-sd_960_540_30fps.mp4'  WHERE skey = 'promo_video_url' AND svalue = 'https://videos.pexels.com/video-files/3571264/3571264-hd_1920_1080_30fps.mp4';
