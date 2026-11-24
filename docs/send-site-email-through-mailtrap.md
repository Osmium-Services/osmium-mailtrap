---
title: Send your site's email through Mailtrap
tags: email, mailtrap, sandbox, testing, test inbox, mail provider
page: admin/settings/mailtrap
---

**Mailtrap** can send real email, or catch it in a test inbox so nothing reaches customers while you test.

## Set it up

1. Open **Mailtrap Settings**.
2. Choose the **Mode**:
   - **Sandbox** delivers every email into a Mailtrap test inbox instead of the real recipient. Enter the **Sandbox Inbox ID**, the number in your Mailtrap inbox address.
   - **Live** sends for real and needs a verified sending domain in Mailtrap.
3. Enter your **API Token**.
4. Press **Save Settings**.
5. Open the **Email** settings page and choose **Mailtrap** as the **Provider**. The **From Address** and **Send test email to** button are there.

Remember to switch to **Live** before the site goes live.
