# Utility Export Feature Implementation Plan

## Overview
Implement an asynchronous export feature for utility usages on a property. The user will be able to export data in three formats: CSV, Excel (.xlsx), and PDF. The context is already bound to a specific property via the application's sidebar, so property selection is omitted from the export flow.

## User Flow
1. **Trigger:** The user is on the `http://127.0.0.1:8001/landlord/utility-usages` page.
2. **Action:** User clicks an "Export Data" button.
3. **Modal Configuration:**
   - **Time Period:** Dropdown or Date picker for Year (e.g., "This Year", "2023") or a custom date range.
   - **Utility Type:** Checkboxes for "All", "Electricity", "Water", etc.
   - **Export Format:** Radio buttons or a dropdown to select CSV, Excel, or PDF.
4. **Execution:** User clicks "Generate Export".
5. **Asynchronous Feedback:** 
   - A success toast appears stating: "Export process started. You will be notified when it's ready."
   - The modal closes immediately, allowing the user to continue working.
6. **Completion:** When the background job completes, the user receives a notification (in-app alert, notification bell, or email) with a link to download the generated file.

## Backend Tasks (Asynchronous Processing)
- [ ] **API Endpoint (Trigger):** Create a new endpoint (e.g., `POST /api/properties/{property_id}/utility-usages/export`) that accepts the filter parameters (date range, utility type, file format).
- [ ] **Job Dispatcher:** 
  - The endpoint validates the request and dispatches a background job to a queue (e.g., using Redis/Celery, BullMQ, Laravel Queues, etc.).
  - The endpoint immediately returns a `202 Accepted` response to the frontend.
- [ ] **Data Fetching (Worker):** 
  - The background worker executes the job. It retrieves all utility records for the property, applying the requested filters.
  - Ensure related data (Room Number, Tenant Name) is eagerly loaded to prevent N+1 queries.
- [ ] **File Generation (Worker):** Implement specific generators for each format:
  - **CSV Generator:** Map the records to a flat structure and write to a `.csv` file.
  - **Excel Generator:** Create a multi-sheet `.xlsx` workbook.
    - *Sheet 1 (Summary):* High-level aggregates (Total cost, monthly breakdown).
    - *Sheet 2 (Raw Data):* The detailed individual records.
  - **PDF Generator:** Generate a polished, printable document. Likely involves rendering an HTML template with the data and converting it to PDF. Should include a summary section and a tabular breakdown.
- [ ] **File Storage (Worker):** Save the generated file to a secure, temporary storage location (e.g., a private S3 bucket or local secure storage). Set an expiration/cleanup policy for old export files.
- [ ] **Notification System (Worker):** Upon successful file generation and storage, trigger a notification to the user containing the secure download link.
- [ ] **API Endpoint (Download):** Create a secure, authenticated endpoint (e.g., `GET /api/exports/{file_id}/download`) to serve the file to the user.

## Frontend Tasks (UI/UX)
- [ ] **Export Button:** Add the "Export Data" button to the utility usages page.
- [ ] **Export Modal:** Build the modal component containing the form inputs (Date Range, Utility Type, Format).
- [ ] **API Integration:** Connect the modal's submit action to the backend trigger endpoint.
- [ ] **UX Feedback:** Implement the loading state on the button and the success toast notification indicating the background job has started.
- [ ] **Notification UI:** Ensure the frontend can display the completion notification (e.g., via WebSockets/polling for an in-app notification bell, or simply relying on backend emails) with a clickable download link.
