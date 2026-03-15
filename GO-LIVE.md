# Get the whole site live at yuliasmassagelab.com

Pick one path and follow it. You need to do the upload yourself (we don’t have access to your hosting).

---

## Fastest: Netlify (no GoDaddy hosting needed)

1. **Zip the site**  
   Select everything inside the `YuliasMassageLab` folder (all HTML, `css`, `js`, `assets`, plus any certificate PDFs you added). Right‑click → **Compress** (Mac) or **Send to → Compressed folder** (Windows). Name it e.g. `yuliasmassagelab-site.zip`.

2. **Deploy to Netlify**  
   - Go to [app.netlify.com](https://app.netlify.com) and sign in (or sign up free).  
   - **Add new site** → **Deploy manually** → **Drag and drop your site folder here**.  
   - Drag the **unzipped** folder (or the zip—Netlify accepts both). Wait for the deploy to finish.

3. **Use your domain**  
   - In the Netlify site: **Domain settings** → **Add custom domain** → type **yuliasmassagelab.com**.  
   - Netlify will show DNS records. In **GoDaddy** → **My Products** → **yuliasmassagelab.com** → **DNS**: add the A and CNAME records Netlify shows. Save.  
   - Wait 5–30 min for DNS. Netlify will then serve your site at yuliasmassagelab.com (and enable HTTPS).

4. **Form**  
   The contact form will work at yuliasmassagelab.com because Formspree allows that domain.

---

## Alternative: Replace WordPress on GoDaddy

If your site is on **GoDaddy Web Hosting** (cPanel):

1. **Back up** the current WordPress site in GoDaddy.
2. Open **File Manager** → go to **public_html**.
3. **Rename** the existing WordPress folder (e.g. to `public_html_old`) or delete the old files so the root is empty.
4. **Upload** the contents of your project into `public_html`:
   - All 6 HTML files in the root.
   - The **css** folder (with `style.css` inside).
   - The **js** folder (with `main.js` inside).
   - The **assets** folder (with all images and any certificate PDFs).
5. Ensure **index.html** is in the root of `public_html`.
6. Visit **yuliasmassagelab.com** and test the form.

---

## What to upload (same for both)

- `index.html`, `services.html`, `techniques.html`, `bio.html`, `bookings.html`, `contact.html`  
- `css/style.css`  
- `js/main.js`  
- `assets/` (logo.png, hero-massage.jpg, services-hands.jpg, bio-studio.jpg, and any cert PDFs you added)

You can ignore `README.md`, `DEPLOY-YULIASMASSAGELAB.md`, and `GO-LIVE.md` when uploading; they’re only for you.
