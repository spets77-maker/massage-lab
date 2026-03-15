# Deploying to yuliasmassagelab.com (GoDaddy)

Your domain is at GoDaddy. You can either host the site **on GoDaddy** or host it on **Netlify/Vercel** and point your GoDaddy domain there.

---

## Option A – Host on Netlify, use GoDaddy domain (recommended)

1. **Deploy the site to Netlify**
   - Go to [app.netlify.com](https://app.netlify.com) and sign up (free).
   - Click **Add new site** → **Deploy manually**.
   - Drag and drop this **entire project folder** (the one with `index.html`, `css/`, `js/`, `assets/`) into the deploy area.
   - Wait for the deploy to finish. Netlify will give you a URL like `random-name.netlify.app`.

2. **Add your GoDaddy domain in Netlify**
   - In the Netlify site dashboard: **Domain settings** → **Add custom domain**.
   - Enter **yuliasmassagelab.com** (and **www.yuliasmassagelab.com** if you want).
   - Netlify will show you which DNS records to create.

3. **Point GoDaddy DNS to Netlify**
   - Log in at [godaddy.com](https://www.godaddy.com) → **My Products** → find **yuliasmassagelab.com** → **DNS** (or **Manage DNS**).
   - **Remove or change** any existing **A** and **CNAME** records that point to WordPress or old hosting (you can note them first if you want to revert).
   - **Add** the records Netlify asks for, for example:
     - **A** record: name `@`, value the IP Netlify gives (e.g. `75.2.60.5`).
     - **CNAME** record: name `www`, value `random-name.netlify.app` (your Netlify site URL).
   - Save. DNS can take 5–60 minutes to update.

4. In Netlify, finish the domain setup (e.g. HTTPS). After DNS propagates, yuliasmassagelab.com will show this static site.

---

## Option B – Host the site on GoDaddy (replace WordPress)

If WordPress is hosted on GoDaddy (Web Hosting or WordPress plan):

1. **Back up first**
   - In your GoDaddy account, use **Backup** or **cPanel → Backup** to back up the site and database.

2. **Open File Manager or use FTP**
   - **GoDaddy Web Hosting:** Go to your hosting product → **cPanel** (or **Web Hosting** dashboard) → **File Manager**.
   - Go to the **document root** (often `public_html`).

3. **Remove or rename the current WordPress files**
   - So the new site can use the root. You can rename the folder to e.g. `public_html_old` if you want to keep WordPress files.

4. **Upload this project**
   - Upload the **contents** of this project into `public_html`:
     - All 6 files: `index.html`, `services.html`, `techniques.html`, `bio.html`, `bookings.html`, `contact.html`
     - Folder **css** (with `style.css` inside)
     - Folder **js** (with `main.js` inside)
     - Folder **assets** (with `logo.png`, `hero-massage.jpg`, `services-hands.jpg`, `bio-studio.jpg` inside)
   - Make sure `index.html` is directly inside `public_html`, not in a subfolder.

5. **Check the site**
   - Visit **yuliasmassagelab.com**. You should see the new static site. If you still see WordPress, clear cache or double-check that `index.html` is in the root and old WordPress `index` files are gone.

**If you use GoDaddy’s managed WordPress:**  
That product is optimized for WordPress. Easiest is to use **Option A** (Netlify + point GoDaddy domain) so you don’t have to change the WordPress plan.

---

## Quick reference – what to upload

```
public_html (or web root)
├── index.html
├── services.html
├── techniques.html
├── bio.html
├── bookings.html
├── contact.html
├── css/
│   └── style.css
├── js/
│   └── main.js
└── assets/
    ├── logo.png
    ├── hero-massage.jpg
    ├── services-hands.jpg
    └── bio-studio.jpg
```

If you tell me whether you’re on **GoDaddy Web Hosting** or **GoDaddy WordPress**, I can narrow the steps further (e.g. exact cPanel or FTP path).
