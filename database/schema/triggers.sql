-- ==========================================
-- 7. TRIGGERS
-- ==========================================
DELIMITER //

CREATE TRIGGER trg_calcular_final_insert
BEFORE INSERT ON Tiene_Inscrita
FOR EACH ROW
BEGIN
    SET NEW.final = (NEW.parcial_1 + NEW.parcial_2 + NEW.parcial_3) / 3.0;
END; //

CREATE TRIGGER trg_calcular_final_update
BEFORE UPDATE ON Tiene_Inscrita
FOR EACH ROW
BEGIN
    SET NEW.final = (NEW.parcial_1 + NEW.parcial_2 + NEW.parcial_3) / 3.0;
END; //

DELIMITER ;