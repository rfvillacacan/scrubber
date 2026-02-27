# 🚀 Introducing Scrubber: Share Logs with AI Safely

Ever needed AI help debugging production logs but worried about exposing sensitive data?

**Scrubber** is here to help! 🔒

## 🎯 What It Does

Scrubber intelligently anonymizes your logs by:
- ✅ Replacing secrets, PII, tokens, passwords
- ✅ Preserving technical context (protocols, ports, versions)
- ✅ Generating realistic fake data (not obvious placeholders)
- ✅ Maintaining structure so AI can actually help troubleshoot

## 📊 Before & After

**Input:**
```
Email: john.doe@bankcorp.internal
JWT: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
IP: 192.168.1.100
```

**Output:**
```
Email: account_3a2f@example.com
JWT: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
IP: 217.89.45.112
```

Labels preserved. Technical context intact. Sensitive data protected. ✨

## ✨ Key Features

**🧠 Smart Context Preservation**
- Keeps `s3://`, ports, versions, infrastructure structure
- Entropy-based detection distinguishes secrets from technical data

**🎨 Realistic Fake Data**
- Emails → `user_3a2f@example.com`
- JWTs → Valid JWT format
- IPs → Valid IP addresses
- IBANs → Country-appropriate format

**🔄 Reversible & Consistent**
- Same value = same fake value throughout document
- Restore original data after AI analysis

## 🛠️ Tech Highlights

- 🐳 Docker deployment (HTTP/HTTPS)
- 📝 JSON-driven rules (easy customization)
- 🔐 Local-first (data never leaves your environment)
- 🎯 10+ built-in rulesets (PII, PCI, PHI, Tokens, Network, Cloud, Banking)

## 🎓 Use Cases

✅ Debug production issues with AI
✅ Create support tickets safely
✅ Share logs with vendors
✅ Write documentation with realistic examples

## 📸 See It In Action

**Screenshot:** ![Scrubber Web UI](docs/images/scrubber-screenshot.png)

**Demo Video:** https://github.com/rfvillacacan/scrubber/blob/main/docs/images/demo.mp4

## 🚀 Get Started

```bash
git clone https://github.com/rfvillacacan/scrubber.git
cd scrubber
cp .env.example .env
docker compose up -d --build
# Open http://localhost:8080
```

## 📦 What's Inside

- ✅ 10+ rulesets, 100+ patterns
- ✅ 18+ data generators
- ✅ Web UI with scrub/restore
- ✅ Session encryption support
- ✅ Quick Test verification

---

🔗 **GitHub:** https://github.com/rfvillacacan/scrubber
📖 **Docs:** https://github.com/rfvillacacan/scrubber/blob/main/README.md

Built for developers who need AI help but care about security. 🛡️

---

**#AI #Security #DevTools #OpenSource #Privacy #DevSecOps #Productivity**

*What's your biggest challenge when sharing logs with AI?*

♻️ Feel free to share if you find this useful!
