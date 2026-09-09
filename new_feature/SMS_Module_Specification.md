# SMS Module Specification

## Purpose

SMS Management sends customer notifications after successful Canteen Due Generation and Cost Cutting operations, using the BulkSMSBD API. SMS failures are logged and do not roll back financial transactions.

## Configuration

Administrators with `sms_api_edit` configure provider URL, balance URL, API key, sender ID, global enablement, and event checkboxes for Due Generation and Cost Cutting. API credentials are never shown in logs.

## Templates

Administrators with `sms_template_edit` manage one enabled template per event. Supported placeholders are `{due_month}` (short format such as `Sep 2026`), `{customer_name}`, `{customer_number}`, `{total_due}`, `{new_due}`, `{cut_amount}`, `{remaining_due}`, `{ledger_url}`, and `{site_name}`.

## Secure ledger URL

Each customer receives a compact opaque token URL such as `/ll/a8K2mP9xQ4`. The URL contains no customer ID or customer number. New tokens are 12 characters and the URL is approximately 39-41 characters on the production domain. The token is stored with a hash and can be revoked or expired from the database. The public page is read-only and is handled by a controller that does not require staff authentication.

## Triggers

- Due Generation: send after the generation transaction commits, using the generated customer total due.
- Cost Cutting: send after the Cost Cutting transaction commits, using the processed cut and remaining amounts.

## Provider contract

Due Generation and Cost Cutting use BulkSMSBD's many-to-many JSON endpoint (`/api/smsapimany`) with `api_key`, `senderid`, and a `messages` array containing `{to, message}` objects. The service sends at most 50 personalized messages per request, so 200 customers become four provider requests. Provider code `202` means sent; all other codes are logged as failed. Each recipient still receives an individual audit log.

## Audit logs

Every attempted send stores event, customer, recipient, template, rendered message, provider code/response, status, error, operator, and timestamp. Logs are available from SMS Management and can be filtered by month.

## Installation

1. Apply `db_structure/sms_schema.sql` after the base schema.
2. Open **SMS Management**, configure the provider and enable the required triggers.
3. Customize templates and test in a non-production environment first.
4. Grant `sms_api_view/edit`, `sms_template_view/edit`, and `send_sms` only to appropriate roles.