ALTER TABLE users
    MODIFY role ENUM('super_admin', 'admin_zone', 'chef_pilier', 'superviseur_pci') NOT NULL;
