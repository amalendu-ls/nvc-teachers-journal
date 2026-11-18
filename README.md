<div align="center">

<img src="./banner.svg" width="100%" alt="Teachers' Journal: OJS 3.3 customisation" />

<br /><br />

<a href="https://journal.nvc.ac.in/"><img src="https://img.shields.io/badge/Visit%20the%20journal-0b1120?style=for-the-badge&logo=openaccess&logoColor=22d3ee" alt="Visit the journal" /></a>
<img src="https://img.shields.io/badge/OJS-3.3-C0392B?style=for-the-badge&labelColor=0b1120" alt="OJS 3.3" />
<img src="https://img.shields.io/badge/ISSN-2395--5627-0b1120?style=for-the-badge&labelColor=0b1120" alt="ISSN 2395-5627" />

</div>

## About

Teachers' Journal is the annual academic journal published by Nabadwip Vidyasagar College. The online edition runs on Open Journal Systems (OJS), covering submissions, issues and archives.

This repository holds the **OJS 3.3 customisation** behind it: a custom theme stylesheet, homepage blocks, an article-statistics plugin and an automated backup.

## OJS 3.3 customisation

### 1. Theme (`ojs-theme/`)

| File | Purpose | Where it goes in OJS |
|---|---|---|
| `tj-custom.css` | Heritage maroon and gold design with a cream canvas and serif headings, including Bengali font support. For the Manuscript (Default child) theme. | Settings → Website → Appearance → Advanced → **Journal style sheet** |
| `homepage-about.html` | "About the Journal" block for the home page: ISSN, frequency, publisher and journal profile cards | Settings → Journal → Masthead → **Journal Summary** |
| `about-journal.html` | About page content | Settings → Journal → Masthead → **About the Journal** |
| `footer.html` | Site footer | Settings → Website → Appearance → **Page footer** |
| `tj-abstract-toggle.html` | Expandable "Abstract" under each article in issue lists. Loads each abstract only when a reader opens it. | Custom Header plugin → **Footer content** |

### 2. Article statistics plugin (`plugins/tjArticleStats/`)

A small generic plugin for OJS 3.3. It is **read-only**: it only reads the existing usage statistics.

- Shows **Abstract views** and **Downloads** under each article in issue tables of contents
- Adds a **Statistics** item to the details column of the article page
- Adds a **Most read** block to the sidebar

**Install:** copy the folder to `plugins/generic/tjArticleStats/`, then enable it under Settings → Website → Plugins.

### 3. Weekly backup (`backup-automation/`)

| Script | Purpose |
|---|---|
| `install-rclone.sh` | One-time: downloads the official rclone build and checks its SHA-256 checksum |
| `tj-backup.sh` | Weekly cron job: dumps the database, packs uploaded files, public files, config and plugins, and copies them to Google Drive. Keeps 12 weeks in Drive and the last 2 runs on the server. |

Database credentials are read at run time from OJS's own `config.inc.php`; none are stored in the script. Replace `<cpanel-user>` with your hosting account's home folder and configure your own rclone remote.

> **Note:** No passwords, account details or personal data are included in this repository.

## Built with

`OJS 3.3` · `PHP` · `CSS` · `HTML` · `JavaScript` · `Bash` · `rclone`

## Maintainer

Built and maintained by **[Dr. Amalendu Das](https://github.com/amalendu-ls)**, Librarian, Nabadwip Vidyasagar College.
[Portfolio](https://sites.google.com/nvc.ac.in/amalendudas/home_1) · [ORCID](https://orcid.org/0000-0003-0799-0599)
