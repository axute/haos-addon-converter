# HAOS Add-on Converter

This tool is a web-based converter that transforms any Docker image into a Home Assistant add-on.

## Features

- **One-Step Form**: The former multi-step wizard has been reduced to a clear one-step form for faster editing.
- **Add-on Documentation (Markdown)**: 
  - Integration of **EasyMDE**, a user-friendly Markdown editor for detailed add-on descriptions.
  - Automatic generation of a `README.md` file within the add-on directory, which Home Assistant uses for the detailed description page in the Add-on Store.
  - Supports syntax highlighting, toolbar, and live preview.
- **Version Management**: Dedicated buttons for **Major**, **Minor**, and **Fix updates** are available when editing add-ons, handling versioning automatically.
- **Storage Mappings (Map)**: Support for Home Assistant storage mappings (`config`, `ssl`, `share`, `media`, `addons`, `backup`) with configurable access modes (`RW`/`RO`).
- **Icon Support**: Upload a custom PNG icon or use the default icon.
- **Ingress Support**: 
  - Configuration of Home Assistant Ingress for seamless access to the web interface.
  - Customizable **Panel Icon** (MDI) for the sidebar.
  - **Ingress Stream** support for WebSockets/VNC.
- **Web UI Configuration**: Automatic generation of the `webui` URL (e.g., `http://[HOST]:[PORT:xxxx]/`) if Ingress is disabled.
- **Port Mappings**: Definition of mappings between container ports and host ports.
- **Backup Integration**: Mark add-ons as backup-compatible (supports `hot` backup mode).
- **Environment Variables**: Flexible definition of environment variables.
  - **Static Variables**: Fixed within the add-on configuration.
  - **Editable Variables**: Can be changed via the Home Assistant GUI after installation. This is achieved using a universal wrapper script.
  - **Risk Note**: Enabling editable variables uses a wrapper script that replaces the container's entrypoint. While it attempts to preserve original behavior, it might cause issues with highly complex Docker images.
- **Smart Entrypoint Preservation**: Uses `crane` to automatically detect and preserve the original `ENTRYPOINT` and `CMD` of any Docker image, even when using the environment variable wrapper.
- **Universal Shell Support**: The wrapper script is written in POSIX-compliant `/bin/sh` and works without heavy dependencies like `jq` or `bash`, ensuring compatibility with minimalist images (e.g., Alpine).
- **Clean Dockerfiles**: The generated `Dockerfile` is kept minimal. For standard add-ons, it uses a simple `FROM` instruction. For add-ons with editable variables, it automatically integrates the wrapper logic.
- **Simplified Config**: The `config.yaml` is kept clean by only including `options` and `schema` when editable variables are actually used.
- **Self-Conversion**: The converter can export itself as a Home Assistant add-on with one click. It uses the official Docker image from GHCR and allows choosing a specific version (tag). It also includes a special icon and `mdi:toy-brick` panel icon.
- **Global Settings**: Configuration of repository name and maintainer in a separate view.
- **Add-on Management**: List, edit, and delete created add-ons.

## Prerequisites

- PHP 8.3 or higher (or Docker)
- Composer (if not run via Docker)

## Installation & Usage

### Option 1: With Docker (Recommended)
You can use the pre-built image from GHCR:
```bash
docker run -d -p 8985:80 -v $(pwd)/data:/var/www/html/data ghcr.io/axute/haos-addon-converter:latest
```
Or use docker-compose:
1. Start the container:
   ```bash
   docker-compose up -d --build
   ```
2. Open the converter in your browser: [http://localhost:8985](http://localhost:8985)

### Option 2: Local with PHP
1. Install dependencies:
   ```bash
   composer install
   ```
2. Start the PHP web server:
   ```bash
   php -S localhost:8000 -t public
   ```
3. Open the converter in your browser: [http://localhost:8000](http://localhost:8000)

## Project Structure

Generated add-ons are created in the `/data/{addon-slug}` directory, as described in the [Home Assistant documentation](https://developers.home-assistant.io/docs/apps/tutorial).

Each add-on directory contains:
- `config.yaml`: Home Assistant configuration
- `Dockerfile`: Based on the selected Docker image
- `README.md`: Detailed add-on description (Markdown)
- `icon.png`: The add-on icon (automatically created during self-conversion or manual upload)
- `run.sh`: (Optional) Wrapper script for environment variable support
- `original_entrypoint` / `original_cmd`: (Optional) Stored metadata for entrypoint preservation

A global `repository.yaml` is maintained in the main data directory.

## Environment Variables

- `CONVERTER_DATA_DIR`: (Optional) Path to the data directory. Default is `./data`. When the converter runs as an HA add-on, this is automatically set to `/addons`.
