# Scrubber Screenshots

This directory should contain screenshots of the Scrubber application for documentation and social media.

## Required Screenshot

**File:** `scrubber-screenshot.png`
**Usage:** Main README.md and LinkedIn post

### Screenshot Guidelines

Capture the Scrubber web UI showing:
- ✅ Raw Input textarea on the left
- ✅ Scrubbed Output textarea on the right
- ✅ Scrub button (clicked state or ready)
- ✅ Session information bar at top
- ✅ Clean, professional appearance

### How to Capture

1. **Start the application:**
   ```bash
   docker compose up -d
   ```

2. **Open in browser:**
   - http://localhost:8080 (HTTP mode)
   - or https://localhost:9443 (HTTPS mode)

3. **Enter sample data:**
   Paste some sample log data with sensitive information:
   ```
   Email: john.doe@example.com
   API Key: sk_live_51N9FakeAPIKeyABC123
   IP: 192.168.1.100
   JWT: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJqb2hu
   ```

4. **Click Scrub** to see the scrubbed output

5. **Take a screenshot:**
   - **Windows:** `Win + Shift + S` (Snipping Tool)
   - **Mac:** `Cmd + Shift + 4` (Screenshot)
   - **Linux:** `Shift + PrtSc` (GNOME)

6. **Save as:** `scrubber-screenshot.png` in this directory

### Recommended Size

- **Width:** 1200-1600 pixels
- **Height:** 800-1000 pixels
- **Format:** PNG for best quality
- **File size:** Keep under 500KB for web performance

### Tips for Great Screenshots

- Use a dark browser theme for contrast
- Include realistic sample data
- Show the scrubbed output with fake values
- Crop out browser chrome for cleaner look
- Consider using a consistent color scheme

---

**Once the screenshot is added**, update these references:
- README.md (line with `![Scrubber Web UI](docs/images/scrubber-screenshot.png)`)
- LINKEDIN_POST.md (add image link in the post)
