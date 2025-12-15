# Refracta

**Your memories, your rules.
A privacy-focused, self-hosted photo and video gallery with granular access control**

## Gallery
Responsive interface featuring dark/light modes and a timeline view for efficient navigation through extensive content.

The gallery displays only public albums or those accessible to the authenticated user.

![Preview](https://github.com/volkar/refracta/blob/main/refracta-gallery.jpg?raw=true)

## Album
Refracta transforms your collections into stunning visual narratives. Albums are rendered via the [Lumosaic](https://github.com/volkar/lumosaic) layout engine, organizing media into a seamless, adaptive grid optimized for all devices. The [Obsidium](https://github.com/volkar/obsidium) overlay provides a silky-smooth, distraction-free lightbox experience, allowing your audience to focus entirely on the moment.

### Title Divider
Within a single album, you can divide a series of photos into several logical sections (e.g., for different days of a single trip) using subheadings and/or text blocks.

### Video Support
Support for video content is integrated, with display behavior consistent with static imagery.

More details about access rights, user groups and administrative interface below.

![Preview](https://github.com/volkar/refracta/blob/main/refracta-album.jpg?raw=true)

Obsidium overlay:

![Preview](https://github.com/volkar/refracta/blob/main/refracta-overlay.jpg?raw=true)

## Administrative Interface

Album creation interface utilizing media from a specified directory. Configuration parameters are auto-populated.

Note the `Resize Large Images` option. Enabled: scales media to maximum dimensions. Disabled: retains original source files (recommended for pre-optimized media).

After an album is created, it will be available in the gallery.

### Albums

After an album is created, it will be available in the album list, where you can view or edit the created album.

### User Groups
Users are organized into groups, each with distinct album access permissions.

### Access Keys
Album access is managed via 32-character authentication keys.

Copy the access link, which includes the access key, and send it to the user.

![Preview](https://github.com/volkar/refracta/blob/main/refracta-admin.jpg?raw=true)

## Edit Album

Album configuration interface for modifying metadata (title, description, date), themes, and access permissions.

Also you can fully delete the album.

### Album Images
Media management interface for reordering or removing content. Supports metadata injection (titles, dividers).

![Preview](https://github.com/volkar/refracta/blob/main/refracta-admin-edit.jpg?raw=true)

## Live demo

See it in action:

[Gallery](https://refracta.syntheticsymbiosis.com/preview?key=no) as a guest (4 albums)

[Gallery](https://refracta.syntheticsymbiosis.com/preview?key=b576763b17013367054c521e07801e36) as a `friends` group user (7 albums)

[Gallery](https://refracta.syntheticsymbiosis.com/preview?key=66be4af32a79ed630a9caef871718786) as a `admin` user (12 albums)

[Admin Panel](https://refracta.syntheticsymbiosis.com/preview/admin.php?key=66be4af32a79ed630a9caef871718786) as a `admin` user

## Installation

### Download

Download the latest release from the [releases page](https://github.com/volkar/refracta/releases/latest) and deploy the files to the target directory.

Next, navigate to the administrative panel:

```
https://your-site.com/target_directory/admin.php
```

This action will generate a base configuration file, create three user groups (`admin`, `family`, and `friends`), establish an access key for the admin group, and display a link to the administrative panel in case access is lost.

**Please save this link in a secure location.**

Next, add your photos and videos to the `_refracta_files/sources/directory_name` directory.

That's it!
Your gallery is ready and you can add albums from the administrative panel.

## Security

Refracta employs 32-character keys for scoped access control. The local backend database is secured against direct frontend access.

Each album's data is stored within a subdirectory identified by a unique 32-character name. This structure prevents unauthorized discovery. Invalid access attempts result in a 404 Not Found error.

### Privacy by Design
Security isn't an afterthought—it's the foundation. Every file uploaded is automatically processed and renamed with a cryptographically secure random string. This obfuscation effectively neutralizes directory enumeration attacks, ensuring that your source filenames are never exposed and your raw assets remain hidden from prying eyes and web scrapers.

### Effortless Access Control
Share with absolute confidence using Refracta's granular permission system. Forget about managing individual user accounts; simply assign albums to specific User Groups (like "Family", "Clients", or "Friends") and share a single unique access link. This key unlocks only the content authorized for that specific group, giving you powerful access control without the friction of a login screen.

## Configuration
Configuration options are stored in the `config.php` file. You can edit it to change default behavior of the gallery.

| Option        | Type  | Default | Description                                                                            |
| ------------- | ----- | ------- | -------------------------------------------------------------------------------------- |
| `GALLERY_ACCESS` | array | `[]`  | Specifies user groups with gallery access. Default (empty) grants universal access.               |
| `THEMES` | array | `['dark', 'light']`   | List of available themes. You can add custom theme styles and add it to this list. |
| `GALLERY_THEME` | string | `dark`  | Default theme for the gallery. |
| `ADMIN_THEME`   | string | `dark`  | Default theme for the admin panel. |
| `EXTRACT_EXIF`   | boolean | `true`  | If true, then the EXIF data will be extracted from the images and displayed in the Obsidium overlay. |
| `IMAGE_MAX_WH`   | integer | `2000`  | Maximum width/height of the image. |
| `PREVIEW_MAX_WH`   | integer | `500`  | Maximum width/height of the preview. |
| `CONVERSION_QUALITY`   | integer | `60`  | Quality of the converted image. 60 is a good balance between quality and file size. |
| `FFMPEG_PATH`   | string | `ffmpeg`  | Path to the ffmpeg executable. Required for video thumbnail generation. |
| `FFPROBE_PATH`   | string | `ffprobe`  | Path to the ffprobe executable (part of ffmpeg library). If ffprobe is not installed, then the video covers can not be generated. |
| `DATE_FORMAT`   | string | `M d, 'y`  | Date format. |

## License

Released under the [MIT License](LICENSE).

## Links

-   [Documentation & Demo](https://refracta.syntheticsymbiosis.com)
-   [GitHub Repository](https://github.com/volkar/refracta)
-   [Latest Release](https://github.com/volkar/refracta/releases/latest)
