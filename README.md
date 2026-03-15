# Yulia's Massage Lab

A simple, elegant 5-page website for a massage therapy practice. Static HTML, CSS, and minimal JavaScript—no build step required.

## Pages

- **Home** — Hero, welcome message, and call-to-action
- **Services** — Session types with brief descriptions and pricing
- **Techniques** — Detailed descriptions of styles, techniques, and tools used in session
- **About** — Bio and approach
- **Book** — Booking request form
- **Contact** — Contact details, hours, and contact form

## Colors & assets

- **Airforce blue** — Primary accent
- **Warm eggshell** — Background
- **Soft charcoal** — Text and footer

Images are in the `assets/` folder. Replace or add images as needed.

## Deploying

The site is static. Deploy the entire project folder to any static host.

### Netlify

1. Sign up at [netlify.com](https://netlify.com).
2. Drag and drop this folder into the Netlify deploy area, or connect a Git repo containing this project.
3. **Build settings:** leave blank (no build command).
4. **Publish directory:** `.` (root).

### Vercel

1. Sign up at [vercel.com](https://vercel.com).
2. Import this project (Git or upload).
3. **Framework:** Other / None.
4. **Root directory:** `.`
5. Deploy.

### GitHub Pages

1. Push this project to a GitHub repository.
2. Settings → Pages → Source: **Deploy from a branch**.
3. Branch: `main` (or your default), folder: **/ (root)**.
4. Save. The site will be at `https://<username>.github.io/<repo>/`.
5. Ensure links work with a base path: either use relative paths (as in this site) or set a base tag if you use a project subpath.

### Other hosts

Upload the contents of this folder via FTP/SFTP or your host’s file manager. Required structure:

```
index.html
services.html
techniques.html
bio.html
bookings.html
contact.html
css/style.css
js/main.js
assets/
  hero-massage.jpg
  services-hands.jpg
  bio-studio.jpg
```

## Forms

The **Book** and **Contact** forms currently show a thank-you message and reset. To receive submissions:

- Use a form backend (e.g. [Netlify Forms](https://docs.netlify.com/forms/setup/), [Formspree](https://formspree.io/), or [Getform](https://getform.io/)), or
- Point the form `action` to your backend endpoint and handle submission there.

Example for Formspree on the contact form:

```html
<form action="https://formspree.io/f/YOUR_FORM_ID" method="post">
```

Do the same for the booking form with a separate form ID if desired.

## Local preview

Open `index.html` in a browser, or run a simple server:

```bash
# Python 3
python3 -m http.server 8000

# Node (npx)
npx serve .
```

Then visit `http://localhost:8000`.
