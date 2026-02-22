# StarGate: Universal API Implementation

**Version**: `0.1.0-alpha`

StarGate is the high-level API implementation for tabular data, built on top of the **StarDust** EAV database abstraction layer. It aims to provide a standardized, RESTful interface capable of serving massive dynamic datasets to modern frontend data grids.

## Architecture

StarGate handles the HTTP layer, standardizing pagination, multi-column sorting, granular filtering (with operators), field projection, and relationship hydration. Behind the scenes, it leverages the CodeIgniter 4 framework and strictly depends on the underlying StarDust library to persist and query dynamic entity structures. 


## Versioning Notice (Alpha Status)

StarGate is currently in an **Alpha** state. 

While its HTTP layer and business logic are tested, feature-complete, and structurally sound, it is deliberately held in alpha because of the state of the underlying `damarbob/stardust` library.

**Important Deployment Pairing:**
This `v0.1.0-alpha` release is explicitly paired with StarDust `v0.2.0-alpha.3`. 

StarDust 0.2.0 relies on a "Virtual Column" indexing architecture which is highly performant for small datasets but triggers strict, low-level InnoDB "row size" limits and metadata locks when scaled to hundreds of dynamic fields or under high concurrency. Because StarGate passes queries directly to StarDust, StarGate's scalability is fundamentally bottlenecked by this infrastructure limitation.

## Roadmap to v1.0

A major architectural shift is planned for the underlying StarDust library in its `v0.3.0` release. StarDust will transition to a **Slot-Based Indexing Strategy** (Sparse Columns) to solve the horizontal scalability constraints and establish enterprise schema stability.

Once StarDust achieves this resilient `0.3.0` architecture, StarGate will be updated in tandem, clearing the path towards its own `0.2.0` (Beta) and eventually `v1.0.0` production-ready release. 

For more details on the scalability limits and the upcoming architecture, please refer to the `FAQ.md`.

## Installation 

*(Instructions tailored for CodeIgniter 4 / Composer)*

```bash
composer update
```

Ensure your `.env` is configured properly for database connectivity.

## Core Feature Overview

- **Dynamic Models**: Serve limitless schemas through the Entries Endpoint. 
- **Universal Querying**: First-class JSON support for queries containing pagination, sorting arrays, and complex operator conditions (`eq`, `neq`, `gt`, `lt`, `like`, `in`).
- **Resource Protection**: Built-in permission enforcing via CodeIgniter Shield. 
- **Sparse Field Projection**: Limit database IO and payload payloads dynamically (`?fields=id,title`).
- **Bulk Delete**: Transactional bulk deletions via JSON payloads.
