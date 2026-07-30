# PowerMedia Gallery - Mac Beginner's Guide

**Don't worry, if you've never done this before, you're in the right place. I'm explaining every single step.**

---

## What You're About to Do

You're going to:
1. Open a program called "Terminal" (it's like a text-based way to talk to your Mac)
2. Tell your Mac to start a website on your computer
3. Open your web browser and see your gallery running

That's it. No installing anything fancy. Your Mac already has everything you need.

---

## Step 1: Find Your Gallery Folder

Look at your screenshot. You can see the folder is at:
```
Downloads > open-source-gallery-main
```

**Translation:** The gallery is in your Downloads folder, in a folder called `open-source-gallery-main`.

---

## Step 2: Open Terminal

This is the "command line" — basically a way to type instructions to your Mac.

**How to open it:**
1. Press **Command + Space** (the Command key is next to the spacebar)
2. Type: `terminal`
3. Press **Enter**

A black window should pop up. That's Terminal. Don't be scared — it's just another way to use your Mac.

---

## Step 3: Go to Your Gallery Folder

In Terminal, you need to navigate to where your gallery lives. Type this and press Enter:

```bash
cd Downloads/open-source-gallery-main
```

**What does this mean?**
- `cd` = "change directory" (go to a folder)
- `Downloads/open-source-gallery-main` = the path to your gallery

After you press Enter, you should see something like:
```
markpower@MacBook Downloads % cd Downloads/open-source-gallery-main
markpower@MacBook open-source-gallery-main %
```

Good! You're now inside the gallery folder.

---

## Step 4: Start the Website

Now type this and press Enter:

```bash
php -S localhost:8080 -t public/
```

**What does this do?**
- `php` = the programming language your gallery runs on (already on your Mac)
- `-S localhost:8080` = start a server on your computer at address `localhost:8080`
- `-t public/` = use the `public` folder as the website folder

After you press Enter, you should see something like:

```
[Wed Jul 29 15:13:19 2026] PHP 8.4.19 Development Server (http://localhost:8080) started
```

**This is good!** It means the server is running.

---

## Step 5: Open Your Gallery in a Browser

Open your web browser (Chrome, Safari, Firefox — whatever you use).

In the address bar at the top, type:

```
http://localhost:8080
```

Press Enter.

**Boom!** You should see your gallery website. It has test photos already loaded so you can see how it works.

---

## Step 6: Create Your Admin Account

1. Click **"Admin"** in the top right corner (or look for a link to the setup page)
2. You'll see a form asking for:
   - **Email:** Type your email address
   - **Password:** Create a password (make it something you'll remember)
3. Click the button to create your account

Done! You're now the admin.

---

## Step 7: Upload Your Photos

1. While logged in as admin, look for **"Upload"** or **"Events"** in the menu
2. Create a new **Event** (this is like a day at the races or a photoshoot)
3. Inside the event, create a **Session** (like a specific time slot or group)
4. Upload your photos into that session
5. Click **"Publish"** to make it visible to customers

Your photos will now show up on the website!

---

## Step 8: Stop the Server (When You're Done)

When you're done for the day and want to close the website:

1. Go back to Terminal
2. Press **Control + C** (hold Control, then press C)

The server stops. Your data is saved. You can always start it again later with the same command.

---

## Troubleshooting

### "Command not found: php"

Your Mac doesn't have PHP. You have two options:

**Option A: Install PHP (recommended)**
1. Install Homebrew first (a Mac package manager):
   ```bash
   /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
   ```
2. Then install PHP:
   ```bash
   brew install php
   ```

**Option B: Use Docker**
If you have Docker, just run:
```bash
docker-compose up
```

### "Address already in use"

Someone else is using port 8080. Try a different port:

```bash
php -S localhost:8081 -t public/
```

Then visit `http://localhost:8081` instead.

### Can't see the website

Make sure:
1. Terminal still shows the "Development Server started" message
2. You're visiting exactly: `http://localhost:8080`
3. Try a different browser (sometimes Safari is weird)

---

## Where Is My Data Stored?

All your photos, events, and settings are saved in a file called:

```
storage/gallery.sqlite
```

This file is in your gallery folder. Don't delete it or you'll lose everything.

If you want to back up your data, just copy this file somewhere safe (like a USB drive or cloud storage).

---

## Next Steps

**Once the website is running:**
1. Add your events and photos
2. Tag your photos (so customers can search)
3. Set prices (how much each photo costs)
4. Publish events (make them visible to customers)
5. Share the website URL with customers

**When you're ready to go live on the internet:**
- Read `SELF_HOSTED.md` in your gallery folder
- It explains how to move this from your Mac to actual hosting (where the real internet can see it)

---

## Still Confused?

If you get stuck:
1. Read the error message Terminal shows you (it usually tells you what's wrong)
2. Make sure you followed each step exactly
3. Check that your gallery folder is really in Downloads

That's it. You've got this. 🎯
