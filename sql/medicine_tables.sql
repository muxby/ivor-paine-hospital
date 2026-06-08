-- ============================================================================-- IVOR PAINE MEMORIAL HOSPITAL-- New Tables for Medicine API, Prescriptions, Reports, and Audit Trail-- Run this AFTER optional_tables.sql-- Safe to run - uses IF NOT EXISTS checks-- ============================================================================

-- ============================================================================-- TABLE: PRESCRIPTION_ITEM-- Stores multiple medicines per prescription-- ============================================================================IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'PRESCRIPTION_ITEM')
BEGIN    CREATE TABLE PRESCRIPTION_ITEM (
        PrescriptionItemID INT IDENTITY(1,1) PRIMARY KEY,
        PrescriptionID INT NOT NULL,
        MedicineApiID VARCHAR(255) NULL,
        MedicineName VARCHAR(255) NOT NULL,
        Price VARCHAR(50) NULL,
        Dosage VARCHAR(100) NULL,
        Frequency VARCHAR(100) NULL,
        Duration VARCHAR(100) NULL,
        Quantity INT NULL,
        Instructions VARCHAR(MAX) NULL,
        CreatedAt DATETIME DEFAULT GETDATE(),
        FOREIGN KEY (PrescriptionID) REFERENCES PRESCRIPTION(PrescriptionID)
    );
    CREATE INDEX IX_PRESCRIPTION_ITEM_PrescriptionID ON PRESCRIPTION_ITEM(PrescriptionID);
    CREATE INDEX IX_PRESCRIPTION_ITEM_MedicineApiID ON PRESCRIPTION_ITEM(MedicineApiID);
END
GO

-- ============================================================================-- TABLE: MEDICINE_API_CACHE-- Caches medicine search results and details to reduce API calls-- ============================================================================IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'MEDICINE_API_CACHE')
BEGIN
    CREATE TABLE MEDICINE_API_CACHE (
        CacheID INT IDENTITY(1,1) PRIMARY KEY,
        MedicineApiID VARCHAR(255) NULL,
        SearchQuery VARCHAR(255) NULL,
        MedicineName VARCHAR(255) NULL,
        Price VARCHAR(50) NULL,
        Discount VARCHAR(50) NULL,
        DetailsJson VARCHAR(MAX) NULL,
        CachedAt DATETIME DEFAULT GETDATE()
    );
    CREATE INDEX IX_MEDICINE_CACHE_ApiID ON MEDICINE_API_CACHE(MedicineApiID);
    CREATE INDEX IX_MEDICINE_CACHE_Query ON MEDICINE_API_CACHE(SearchQuery);
    CREATE INDEX IX_MEDICINE_CACHE_CachedAt ON MEDICINE_API_CACHE(CachedAt DESC);
END
GO

-- ============================================================================-- TABLE: API_LOG-- Tracks all external API calls for monitoring and debugging-- ============================================================================IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'API_LOG')
BEGIN
    CREATE TABLE API_LOG (
        ApiLogID INT IDENTITY(1,1) PRIMARY KEY,
        Endpoint VARCHAR(255),
        QueryValue VARCHAR(255),
        Status VARCHAR(50),
        ResponseTimeMs INT NULL,
        ErrorMessage VARCHAR(MAX) NULL,
        CreatedAt DATETIME DEFAULT GETDATE()
    );
    CREATE INDEX IX_API_LOG_CreatedAt ON API_LOG(CreatedAt DESC);
    CREATE INDEX IX_API_LOG_Status ON API_LOG(Status);
END
GO

-- ============================================================================-- TABLE: AUDIT_LOG-- Comprehensive audit trail for all important system actions-- ============================================================================IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'AUDIT_LOG')
BEGIN
    CREATE TABLE AUDIT_LOG (
        AuditID INT IDENTITY(1,1) PRIMARY KEY,
        UserName VARCHAR(100) NULL,
        ActionType VARCHAR(100) NOT NULL,
        EntityType VARCHAR(100) NOT NULL,
        EntityID VARCHAR(100) NULL,
        Description VARCHAR(MAX) NULL,
        CreatedAt DATETIME DEFAULT GETDATE()
    );
    CREATE INDEX IX_AUDIT_LOG_CreatedAt ON AUDIT_LOG(CreatedAt DESC);
    CREATE INDEX IX_AUDIT_LOG_ActionType ON AUDIT_LOG(ActionType);
    CREATE INDEX IX_AUDIT_LOG_EntityType ON AUDIT_LOG(EntityType);
    CREATE INDEX IX_AUDIT_LOG_UserName ON AUDIT_LOG(UserName);
END
GO

-- ============================================================================-- TABLE: PRESCRIPTION (if not exists - ensures base table is there)-- ============================================================================IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'PRESCRIPTION')
BEGIN
    CREATE TABLE PRESCRIPTION (
        PrescriptionID INT IDENTITY(1,1) PRIMARY KEY,
        ApptID INT NOT NULL,
        Medication VARCHAR(255) NULL,
        Dosage VARCHAR(100) NULL,
        Frequency VARCHAR(100) NULL,
        IssuedDate DATETIME DEFAULT GETDATE(),
        FOREIGN KEY (ApptID) REFERENCES APPOINTMENT(ApptID)
    );
END
GO
-- ============================================================================-- Add trigger to log prescription creation automatically (optional)-- ============================================================================
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'AUDIT_LOG')
BEGIN
    IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'PRESCRIPTION')
    BEGIN
        IF NOT EXISTS (SELECT * FROM sys.triggers WHERE name = 'TR_PRESCRIPTION_AUDIT')
        BEGIN
            EXEC('
            CREATE TRIGGER TR_PRESCRIPTION_AUDIT
            ON PRESCRIPTION
            AFTER INSERT
            AS
            BEGIN
                SET NOCOUNT ON;
                INSERT INTO AUDIT_LOG (UserName, ActionType, EntityType, EntityID, Description, CreatedAt)
                SELECT
                    SUSER_SNAME(),
                    ''create'',
                    ''prescription'',
                    CAST(i.PrescriptionID AS VARCHAR),
                    ''Prescription created for appointment '' + CAST(i.ApptID AS VARCHAR),
                    GETDATE()
                FROM inserted i;
            END
            ')
        END
    END
END
GO

-- ============================================================================-- Add trigger for patient registration audit-- ============================================================================
IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'AUDIT_LOG')
BEGIN
    IF EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'PATIENT')
    BEGIN
        IF NOT EXISTS (SELECT * FROM sys.triggers WHERE name = 'TR_PATIENT_AUDIT')
        BEGIN
            EXEC('
            CREATE TRIGGER TR_PATIENT_AUDIT
            ON PATIENT
            AFTER INSERT, UPDATE
            AS
            BEGIN
                SET NOCOUNT ON;
                IF EXISTS(SELECT 1 FROM inserted i LEFT JOIN deleted d ON i.PatientID = d.PatientID WHERE d.PatientID IS NULL)
                BEGIN
                    INSERT INTO AUDIT_LOG (UserName, ActionType, EntityType, EntityID, Description, CreatedAt)
                    SELECT SUSER_SNAME(), ''create'', ''patient'', CAST(i.PatientID AS VARCHAR),
                           ''Patient registered: '' + i.PatientName, GETDATE()
                    FROM inserted i LEFT JOIN deleted d ON i.PatientID = d.PatientID WHERE d.PatientID IS NULL;
                END
                ELSE
                BEGIN
                    INSERT INTO AUDIT_LOG (UserName, ActionType, EntityType, EntityID, Description, CreatedAt)
                    SELECT SUSER_SNAME(), ''update'', ''patient'', CAST(i.PatientID AS VARCHAR),
                           ''Patient updated: '' + i.PatientName, GETDATE()
                    FROM inserted i WHERE EXISTS(SELECT 1 FROM deleted d WHERE d.PatientID = i.PatientID);
                END
            END
            ')
        END
    END
END
GO

PRINT 'All medicine, prescription, cache, API log, and audit tables created successfully.';
