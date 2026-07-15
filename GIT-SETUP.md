# Setting up Git deployment on Český hosting

## How Český hosting's Git actually works

This is NOT "connect your GitHub repo." Český hosting hosts its **own** Git
repository (a "Git archiv") on their server, reachable over SSH. The model is:

```
   your PC                    Český hosting
   ┌────────┐   git push      ┌──────────────┐
   │  local │ ───────────────>│  Git archiv  │
   │  repo  │                 │      │        │
   └────────┘                 │      │ deploy │
                              │      v        │
                              │   www/  (live site)
                              └──────────────┘
```

You push from your PC to their Git archive. A hook on their side checks the
files out into your web root. No FTP, no GitHub Action — one `git push`
publishes the site.

**GitHub still fits in** — as a second remote, purely for backup and history.
You push to both. More on that at the end.

---

## Step 1 — Create the Git archive

On the Git page you screenshotted:

1. Enter a **password** (min 8 chars, and NOT containing `" ; ' < > \`).
   This is the password for pushing over Git — write it down.
2. Click **"Vytvořit Git archiv"**.

After it's created, the page will show you the **repository URL**. It looks
roughly like:
```
ssh://username@pauwels-freelance.cz/git/pauwels-freelance.cz
```
Copy the exact one they give you — the username and path are specific to your
account.

---

## Step 2 — Install Git on your PC (if you haven't)

Download from git-scm.com. Then set your identity once:
```bash
git config --global user.name  "Michael Pauwels"
git config --global user.email "you@example.com"
```

---

## Step 3 — Turn your site folder into a Git repo

Open a terminal **in the folder that contains index.html** and run:

```bash
git init
git add .
git status          # <-- READ THIS. Confirm config.php is NOT listed.
git commit -m "Initial commit: Pauwels Freelance site"
```

The `git status` check matters. `.gitignore` should keep `config.php` and
`vendor/` out. If you see `config.php` in the list, STOP — the .gitignore
isn't in place, and you're about to commit your mailbox password.

---

## Step 4 — Connect the Český hosting remote and push

```bash
git remote add hosting ssh://username@pauwels-freelance.cz/git/pauwels-freelance.cz
git push hosting main
```

(Use the exact URL from step 1. If your branch is called `master` not `main`,
push that instead — `git branch` tells you which you have.)

First push will ask you to accept the server's SSH fingerprint (type `yes`)
and then the Git password from step 1.

---

## Step 5 — Point the deployment at your web root

Český hosting's Git archive is separate from `www/` by default. You need their
deploy hook to check files out into the web root. This part varies by account,
and their control panel usually has a setting for the **target directory** (the
"pracovní adresář" / working directory) — set it to your web root, typically
`www/` or `web/`.

**If you can't find that setting, this is the one thing to open a support
ticket for.** Ask: *"How do I configure the Git archiv for pauwels-freelance.cz
to deploy pushed files into the web root?"* They'll know exactly what you mean.
Don't fight it for an hour — this is genuinely their-side configuration.

---

## Step 6 — Upload the server-only files ONCE

Some files are deliberately NOT in Git (`config.php`, `vendor/`). Upload these
by FTP a single time — they don't change often:

1. **config.php** — copy from config.sample.php, fill in the real mailbox
   password, upload to the web root.
2. **vendor/** — run `composer install` locally, then FTP-upload the whole
   `vendor/` folder. (Or install PHPMailer manually per DEPLOY.md.)

After this, your normal workflow is just: edit → commit → push. Only the site
files travel through Git; the secrets and libraries stay put on the server.

---

## Everyday workflow after setup

```bash
# make your edits, then:
git add .
git commit -m "Update pricing"
git push hosting main       # publishes to the live site
git push github  main       # backup to GitHub (see below)
```

---

## Adding GitHub as a backup remote

Create a **private** repo on GitHub (no README, empty). Then:

```bash
git remote add github https://github.com/YOURNAME/pauwels-freelance.git
git push github main
```

Now you have two remotes:
- `hosting` — deploys the live site
- `github`  — backup + history

You can push to both with one command by editing `.git/config`, but pushing
twice is fine and explicit while you're getting used to it.

**Keep the GitHub repo private.** Even with config.php gitignored, there's no
reason for your site's source to be public, and one mistake exposes everything.

---

## The golden rule

Before EVERY first push to a new remote, run `git status` and confirm
`config.php` is not listed. A password committed to Git history stays there
even after you delete the file — and if it ever reaches GitHub, treat it as
compromised: change the mailbox password immediately.
