# Singularity

**Singularity** is a next-generation IDE designed to build web applications with the architectural rigor and elegance of the **Cocoa** ecosystem.

By bridging the gap between desktop-grade software engineering and web development, Singularity brings the power of **Foundation** and **Core Data** to **PHP 8.3+**. It provides a robust, visual environment to design data models, manage persistence, and maintain data integrity through a desktop experience powered by **Electron**.

## 🚀 The Vision

The goal of Singularity is to evolve web development into a structured engineering discipline. By implementing faithful ports of Apple's core frameworks, Singularity allows developers to use familiar patterns like **Key-Value Observing (KVO)**, **Managed Object Contexts**, and **Model-Driven Development** within the PHP ecosystem.

## ✨ Current Features

- **Visual Model Editor**: A professional interface for defining Entities, Attributes, and Relationships, inspired by Xcode’s Data Model Inspector.
- **Core Data for PHP**: A full-featured persistence layer including `ManagedObject`, `FetchRequests`, and `Delete Rules`.
- **Advanced KVO System**: Native support for Key-Value Observing, enabling reactive business logic and consistent state management.
- **Rich Data Modeling**:
    - **Composite Types**: Define custom, reusable data structures.
    - **Fetched Properties**: Dynamic properties based on stored predicates.
    - **Inheritance**: Support for Superentities and Subentities.
- **Schema & Code Generation**:
    - Automatic generation of `ManagedObject` subclasses.
    - Live SQL Schema visualization and export.
- **Index Management**: Comprehensive support for `FetchIndex` with collation types and partial index predicates.

## 🛠 Tech Stack

- **Runtime**: [Electron](https://www.electronjs.org/) for a seamless cross-platform desktop experience.
- **Backend**: **PHP 8.3+** (leveraging modern features like *Property Hooks*).
- **Frontend**: [Latte Templates](https://latte.nette.org/) for clean, secure, and compiled UI rendering.
- **Framework Core**: Built upon the `Sabatier\Foundation` and `Sabatier\CoreData` ecosystems.

## 🗺 Roadmap

- [ ] **Interactive Entity Diagram**: A graphical canvas to visualize and connect entities through drag-and-drop relationships.
- [ ] **Interface Builder**: A visual editor for web components, bringing the `.xib` workflow to the browser.
- [ ] **Native Data Bindings**: Automatic UI-to-Model synchronization.
- [ ] **Migration Engine**: Versioned model hashing and automated SQL migrations.

## 📦 Getting Started

*(Add your specific installation steps here, for example:)*

```bash
# Clone the repository
git clone https://github.com/your-repo/singularity.git

# Install PHP dependencies
composer install

# Install JS dependencies & launch
npm install
npm start
```
