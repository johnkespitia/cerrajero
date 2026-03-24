# AGENTS.md

## Scope

Estas reglas aplican a `cerrajero/**`.

## Role

`cerrajero` es un repo de implementacion de codigo.

## Required reading order

1. la spec del root que dispara el trabajo
2. `campoverde-manager/specs/000-foundation/**` relevantes
3. este archivo

## Suggested agent skills

- `workspace/spec-driven-workspace`
- `workspace/node-core`

## Rules

- no crear specs locales como fuente de verdad paralela
- implementar solo lo que este cubierto por `targets` del root
- mantener tests y codigo alineados con la spec del root
- si el cambio requiere ampliar alcance, actualizar primero la spec del root
