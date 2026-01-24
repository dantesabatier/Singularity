# Singularity

**Build apps the way desktop apps were meant to be built.**

Singularity is an application development environment where **persistence, identity, and lifecycle** are first-class concepts.

It is not a framework in the traditional sense.  
It is a system for building applications around **living object graphs**, not around routes, controllers, or boilerplate infrastructure.

---

## 🌌 The Vision

Modern web development often treats persistence as an implementation detail and application state as something that must be reconstructed on every request.

Singularity takes the opposite approach.

Singularity brings the architectural principles of classic desktop application development — those pioneered by **Cocoa**, **Core Data**, and **AppKit** — into the PHP ecosystem.

It enables developers to build applications where:

- objects have **identity**
- state has **continuity**
- data has a **lifecycle**
- persistence is **described, not programmed**

The goal is not to replace existing web frameworks, but to evolve application development into a more structured engineering discipline — one where complexity is managed through **models**, not scattered glue code.

---

## 🧠 What Makes Singularity Different

Singularity is designed around a simple but powerful idea:

> **The data model is the application.**

From this premise, everything else follows.

---

### • Model-Driven by Design

Applications are defined visually through a professional data modeling editor inspired by Xcode’s Core Data Model Inspector.

Entities, attributes, relationships, inheritance, constraints, and versioning are all **first-class concepts**, not annotations layered on later.

---

### • Real Object Graph Management

Singularity includes a faithful port of Core Data concepts, including:

- managed object contexts
- identity-preserving fetches
- snapshots and change tracking
- faulting and refresh cycles
- relationship integrity and cascading behavior

Objects are not recreated on demand.  
They **live**, **change**, and **evolve** within a context.

---

### • Lifecycle-Aware Architecture

Applications built with Singularity have a true lifecycle:

- initialization
- fetch
- refresh
- update
- persistence
- fault resolution

This lifecycle exists even if you write **zero lines of application logic**.

---

### • Reactive by Nature

Native support for **Key-Value Observing (KVO)** enables consistent, declarative reactions to state changes across the system — from persistence to UI.

No manual synchronization.  
No fragile glue code.

---

### • Minimal Code, Maximum Signal

There are no routes to declare.  
No controllers to scaffold.  
No persistence layers to hand-roll.

Code is written **only where behavior is needed** — not to move data around.

---

## 🧩 Core Components

### Visual Model Editor
Design complex schemas, relationships, and constraints visually, with live feedback and structural guarantees.

### Core Data Port (`Sabatier\CoreData`)
A full object-graph and persistence system, including snapshots, identity tracking, and fault handling.

### Foundation Layer (`Sabatier\Foundation`)
A comprehensive base framework providing object lifecycles, collections, dictionaries, observation, and runtime behavior.

### Service Layer
An application runtime inspired by AppKit and UIKit, adapted for web and service environments.

### Automatic Code & Schema Generation
Managed object subclasses, SQL schemas, and migrations are generated from the model — not handwritten.

---

## 🛠 Technology Stack

**Runtime**  
Electron — delivering a native, cross-platform desktop experience.

**Backend**  
PHP 8.3+, using modern language features and strict object-oriented design.

**Frontend Rendering**  
Latte Templates for secure, compiled, and expressive UI generation.

**Framework Ecosystem**  
Built on `Sabatier\Foundation` and `Sabatier\CoreData`.

---

## 🎯 What Singularity Is — and Is Not

### Singularity *is*:
- an application development environment
- model-centric
- lifecycle-aware
- persistence-native

### Singularity *is not*:
- a CRUD generator
- a low-code tool
- an MVC framework
- a replacement for every web stack

It is a tool for developers who value **architecture**, **correctness**, and **long-term evolution** over short-term convenience.

---

## 🧬 Inspiration

Singularity is deeply inspired by decades of engineering excellence in:

- NeXTSTEP
- Cocoa
- Core Data
- AppKit

Its purpose is not nostalgia, but **continuity** — preserving ideas that solved hard problems long ago and applying them to modern application development.

---

Developed with ❤️ by the Sabatier team.  
Built for engineers who believe software should have **structure**, **memory**, and **identity**.
