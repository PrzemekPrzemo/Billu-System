-- v8.1: Store KSeF XML content for invoice visualization
ALTER TABLE invoices ADD COLUMN ksef_xml MEDIUMTEXT NULL AFTER ksef_reference_number;
