# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0.alpha.1] - 2026-02-23

### Added

- **Project Documentation**: Added StarGate project documentation and FAQ (b003942).
- **Entries API**:
  - Implemented entries CRUD API and documentation (615fb2e).
  - Added batch deletion to Entries and Models APIs (fde6ee5).
- **Permissions & Security**:
  - Added API permission filter for JSON responses (1f421fd).
  - Added `models.manage` and entries management permissions to `AuthGroups` (e861f60, d64afd4).
- **Core API Features**:
  - Adopted `RequestQueryParser` in Models index endpoint (858076c).
  - Added `RequestQueryParser` library (87e2877).
- **Localization**: Added entry language strings to StarGate (660ab09).
- **Testing Infrastructure**:
  - Added `RealModelsSeeder` for realistic test data generation (1aa48d0).
  - Added `RealModelsPurger` seeder and integration test for seeder cycle (ef9b7da, bf37f89).
  - Added feature tests for Models API endpoints (14844bc, 72155b7).

### Changed

- **Dependencies**: Updated StarDust dependency to `v0.2.0-alpha.3` (a67c014).
- **Models API**:
  - Refactored Models API and enforced permissions (2ed99ed).
  - Delegated input filtering to `ModelsManager` in Models controller (a6590ce).
  - Refactored sparse fieldsets filtering to query level in Models API (7a69f12).

### Fixed

- **Testing**:
  - Mocked `RuntimeIndexer` in `RealModelsSeederTest` for SQLite (bd72c30).
  - Fixed CLI usage and reduced model count in `RealModelsSeeder` (2a93e6c).
