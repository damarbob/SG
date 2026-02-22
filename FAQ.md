# Frequently Asked Questions (FAQ)

## What is the relationship between StarGate and StarDust?

StarGate is the **high-level, HTTP API layer**, and StarDust is the **low-level, Database abstraction layer**.

Think of StarDust as the engine moving the data in and out of MySQL utilizing the Entity-Attribute-Value (EAV) paradigm over JSON. StarGate acts as the steering wheel and chassis: it defines the routing, processes the incoming HTTP query strings (sorting, pagination, sparse fieldsets), enforces authorization logic, and standardizes the outgoing JSON responses for frontend frameworks. 

## Why is StarGate versioned at 0.1.0-alpha when StarDust is at 0.2.0-alpha?

They follow distinct versioning lifecycles based on their feature maturity. Although StarGate relies on StarDust, StarGate itself is a newly stabilized codebase. Marking it `0.1.0-alpha` formally establishes the baseline for its feature-complete API layer (the Entries CRUD, query logic, and validation rules).

## Is StarGate ready for production?

**No.** While the StarGate API code passes its integration tests and contains no known major bugs, it is constrained by the architecture of StarDust `0.2.0-alpha.3`. 

Currently, StarDust relies on dynamic DDL operations (MySQL Virtual Generated Columns) to index user-defined data. In a massive-scale production environment—where hundreds of fields are defined or continuous schema changes occur under high concurrency—this underlying architecture will bottleneck, leading to metadata locks or crashing against InnoDB's 65,535-byte row limit.

## What is the roadmap to production readiness?

We must resolve the fundamental infrastructure limits within StarDust before we can declare StarGate production-ready.

The immediate engineering priority is refactoring StarDust for version `0.3.0` to utilize a **Slot-Based Indexing Strategy** (Sparse Columns). This metamorphosis will decouple the logical application fields from physical `ALTER TABLE` operations, effectively solving the scalability crisis. 

Once StarDust `0.3.0` is deployed and stable, StarGate will integrate those changes and be promoted to `v0.2.0` (or beta), at which point it will be cleared for high-scale environments.
