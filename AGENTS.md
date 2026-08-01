# AGENTS.md

## Project Overview
The **ESPACE** FastAPI backend acts as an intermediary between a Flutter client and a Moodle instance. All data originates from Moodle, and the backend exposes a stable, type‑hinted REST API that the Flutter app consumes.

## Core Principles (Requirements)

- **Moodle is the source of truth** – Every piece of information (courses, activities, users, grades, etc.) is fetched from Moodle via its web‑service API. The backend never stores duplicate state.
- **Flutter never talks to Moodle directly** – The Flutter client only calls the FastAPI endpoints defined in this repository.
- **Routers stay thin** – Router functions only handle request validation, authentication, and delegation to service functions.
- **Business logic lives in services** – All interactions with Moodle, data transformation, and error handling are implemented in the `app/services/` package.
- **API compatibility is preserved** – Existing endpoint signatures, response models, and status codes remain unchanged.
- **Python type hints are used everywhere** – All public functions, service helpers, and router dependencies are annotated for static analysis and IDE support.
- **Minimal, architecture‑consistent changes** – New code is added only where it belongs; no unrelated files are touched.

## Implementation Plan

1. **Create `AGENTS.md`** at the repository root.
2. Document the responsibilities of each “agent” (router, service, security, config) to make the architectural intent explicit for future contributors.
3. Keep the file purely informational; no code changes are required.
4. Ensure the markdown follows the existing documentation style (e.g., similar to `README.md`).

## Agents Overview

| Agent            | Location               | Responsibility |
|------------------|------------------------|----------------|
| **Router Agent** | `app/routers/`         | Accepts HTTP requests, validates input with Pydantic models, extracts the JWT token, and forwards the call to the appropriate Service Agent. |
| **Service Agent**| `app/services/`        | Contains all business logic: calls Moodle via `services/moodle.py`, normalizes responses, handles pagination, and raises domain‑specific exceptions. |
| **Security Agent**| `app/security.py`     | Generates and validates JWT access tokens, ensuring that only authenticated Flutter clients can reach the Service Agents. |
| **Config Agent** | `app/config.py`        | Provides environment‑specific settings (Moodle URL, secret keys, etc.) to all other agents. |
| **Model Agent**  | `app/models/`          | Defines request and response schemas using Pydantic, guaranteeing type safety across the stack. |
| **Moodle Plugin** | `local_espace/`        | Moodle 5.2 local plugin for official-WS gaps. Install as `{moodle}/local/espace/`. |

## How to Extend

When adding new functionality:

1. **Add a new endpoint** in the appropriate router (or create a new router) – keep the function body limited to `return service.some_action(...)`.
2. **Implement the logic** in a new or existing service module – use type hints for all parameters and return values.
3. **Create or update Pydantic models** if the request/response shape changes.
4. **Write tests** that target the service layer; router tests can be thin smoke tests.
5. **Verify the Moodle Web Service exists** in Moodle 5.2 before implementing. If it does not, return a structured unsupported payload and mark `TODO(local_espace)`.

## local_espace Extension Points

When official Moodle WS cannot support a capability, ESPACE exposes a clear unsupported response instead of inventing APIs:

| Extension | Purpose |
|-----------|---------|
| `local_espace_rename_section` | Rename section / edit section summary |
| `local_espace_upsert_module` | Full create/edit of module settings, files, HTML content |
| `local_espace_set_availability` | Restrict access conditions |
| `local_espace_set_completion` | Configure activity completion settings |

See `docs/MOODLE_WS_CAPABILITIES.md`.

Following this plan ensures the backend remains a clean, maintainable bridge between Flutter and Moodle while respecting the constraints listed above.

---  
*This document is intended for developers working on the ESPACE FastAPI backend.* 
