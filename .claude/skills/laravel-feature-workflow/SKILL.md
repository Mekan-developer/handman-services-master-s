---
name: laravel-feature-workflow
description: Full-stack build order for a new Laravel feature (Migration → Repository → Service/Action → Controller → Form Request/Resource → Vue Component) plus the README-maintenance checklist. Invoke when asked to build/scaffold a new feature, or when adding a package, a new architectural pattern, or a new environment variable.
---

## Implementation Workflow

When asked to build a feature, provide the full stack in this order:
1. Migration & Model
2. Repository
3. Service/Action
4. Controller
5. Form Request & Resource
6. Vue Component (with i18n & Dark Mode)

## README Maintenance

- Always update `README.md` when adding new packages, services, or major architectural changes to the project.
- When adding a package: update the Tech Stack table and document its usage.
- When adding a new pattern or convention: add it to the Architecture & Patterns section.
- When adding new environment variables: update the Environment Variables section and `.env.example`.
