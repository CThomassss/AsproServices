-- Create promotions table for PHP admin and Next.js export
CREATE TABLE IF NOT EXISTS promotions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  discount VARCHAR(255) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  validUntil DATE DEFAULT NULL,
  imageUrl VARCHAR(1024) DEFAULT NULL,
  pdfUrl VARCHAR(1024) DEFAULT NULL,
  createdAt DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Optional: example insert (uncomment and adjust paths as needed)
-- INSERT INTO promotions (title, discount, description, validUntil, imageUrl, pdfUrl)
-- VALUES ('Offre exemple', '10%', 'Remise sur outillage pro', '2025-12-31', '/promotions/exemple.jpg', '/promotions/exemple.pdf');
