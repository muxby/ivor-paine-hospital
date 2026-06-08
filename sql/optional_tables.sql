-- ============================================================================
-- IVOR PAINE MEMORIAL HOSPITAL
-- Optional Enhancement Tables
-- Run these only if you want the additional features
-- They are safe to add and do not affect existing data
-- ============================================================================

-- Activity Log for timeline tracking
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'ACTIVITY_LOG')
BEGIN
    CREATE TABLE ACTIVITY_LOG (
        LogID INT IDENTITY(1,1) PRIMARY KEY,
        ActionType NVARCHAR(50) NOT NULL,
        EntityType NVARCHAR(50) NOT NULL,
        EntityID INT NOT NULL,
        Details NVARCHAR(500) NULL,
        LoggedAt DATETIME NOT NULL DEFAULT GETDATE()
    );
    CREATE INDEX IX_ACTIVITY_LOG_LoggedAt ON ACTIVITY_LOG(LoggedAt DESC);
    CREATE INDEX IX_ACTIVITY_LOG_Entity ON ACTIVITY_LOG(EntityType, EntityID);
END
GO

-- Patient audit trail (tracks bed assignment changes)
IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'PATIENT_BED_HISTORY')
BEGIN
    CREATE TABLE PATIENT_BED_HISTORY (
        HistoryID INT IDENTITY(1,1) PRIMARY KEY,
        PatientID INT NOT NULL,
        OldBedNumber INT NULL,
        NewBedNumber INT NULL,
        ChangedAt DATETIME NOT NULL DEFAULT GETDATE(),
        ChangedBy NVARCHAR(50) NULL DEFAULT SUSER_SNAME()
    );
END
GO
