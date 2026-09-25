---
title: Agents Configuration
---

# Agent Documentation

## Overview
This document outlines the agent configuration and responsibilities within the `cerrajero` project.

## Responsibilities
- Deployment automation
- Environment management
- CI/CD integration
- Monitoring setup

## Configuration
```yaml
agents:
  - name: deploy-agent
    role: deployment
    commands:
      - npm run build
      - git push origin main

  - name: monitor-agent
    role: observability
    commands:
      - pm2 start ecosystem.config.js
      - curl https://api.example.com/health
```

## Notes
1. Agents are defined in `.github/workflows/` for CI/CD integration
2. Review `next.config.js` for environment-specific configurations
3. Ensure all agents have proper authentication tokens stored securely