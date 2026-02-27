## 🖼️ Image for Post

**Use this screenshot:** `docs/images/scrubber-screenshot.png`

**Alt text:** Scrubber Web UI - showing log anonymization tool with raw input and scrubbed output panels

---

# 🚀 Excited to Share: Scrubber - The AI-Friendly Log Anonymization Tool!

I'm thrilled to announce **Scrubber**, an open-source tool I've been working on that solves a growing problem for developers: **How do you safely share logs with AI assistants without exposing sensitive data?**

## 🤔 The Problem

We've all been there:
- Needing AI help debugging production issues
- But our logs contain **secrets, PII, tokens, passwords**
- Redacting manually is tedious and error-prone
- Simple redaction breaks log structure → AI can't help effectively

## ✨ The Solution: Scrubber

**Scrubber** intelligently anonymizes your logs while preserving technical context, making them safe to share with AI assistants like Claude, ChatGPT, or GitHub Copilot.

### 🎯 Key Features

**🧠 Smart Context Preservation**
- Preserves protocols (`s3://`, `https://`)
- Keeps ports (`:5000/`, `:443/`) and versions (`:alpine`, `:v1.2.3`)
- Maintains infrastructure structure for effective troubleshooting

**🔒 Intelligent Detection**
- JSON-driven rule configuration (easy to customize!)
- Entropy-based detection distinguishes secrets from technical data
- 10+ built-in rulesets: PII, PCI, PHI, Tokens, Network, Cloud, Banking, Finance, Corporate, General

**🎨 Realistic Fake Data**
- Emails → `user_3a2f@example.com`
- JWTs → Valid JWT format with fake payload
- IPs → Valid IP addresses
- UUIDs → Properly formatted UUIDs
- IBANs → Country-appropriate format

**🔄 Reversible & Consistent**
- Same value always maps to same fake value throughout document
- Restore original data after AI analysis
- Session-based storage with optional encryption

## 📊 Before & After

**Input (Sensitive):**
```
Email: john.doe@bankcorp.internal
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
Card: 4111 1111 1111 1111
S3: s3://prod-customer-data/invoices/
```

**Output (Safe to Share):**
```
Email: account_3a2f@example.com
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
Card: 4532015112830366
S3: s3://fake-invoice-bucket/Xyz9/
```

**Notice:** Labels preserved (`Email:`, `Authorization:`), technical context intact (`s3://`), but sensitive data replaced with realistic fake values!

## 🎬 Watch the Demo

**See Scrubber in action:** https://github.com/rfvillacacan/scrubber/blob/main/docs/images/demo.mp4

The demo shows:
- Pasting raw log data with sensitive information
- One-click scrubbing with realistic fake data
- Technical context preservation for AI readability
- Quick Test verification for accurate restoration

## 🛠️ Technical Highlights

- **Local-first architecture** - No external API calls, data never leaves your environment
- **Docker deployment** - One-command setup with HTTP or HTTPS modes
- **JSON-driven rules** - Add custom patterns without touching code
- **Priority-based processing** - Higher priority rules prevent overlap conflicts
- **Global caching** - Ensures consistency across entire documents

## 🎓 Use Cases

✅ **Debugging production issues** with AI assistance
✅ **Creating support tickets** without exposing sensitive data
✅ **Writing documentation** with realistic examples
✅ **Sharing logs** with external teams or vendors
✅ **Compliance reporting** with anonymized data

## 🌟 What Makes It Different

Unlike simple redaction tools, Scrubber:
- **Preserves structure** → AI can understand the log format
- **Maintains context** → Technical details remain intact
- **Generates realistic data** → AI can reason about the patterns
- **Smart entropy detection** → Knows the difference between secrets and infrastructure

## 🔗 Get Started

```bash
git clone https://github.com/rfvillacacan/scrubber.git
cd scrubber
cp .env.example .env
docker compose up -d --build
# Open http://localhost:8080
```

## 📦 What's Inside

- ✅ 10+ bundled rulesets covering 100+ patterns
- ✅ 18+ data generators (emails, IPs, UUIDs, JWTs, IBANs, etc.)
- ✅ Web UI with scrub/restore functionality
- ✅ Session management with encryption support
- ✅ Quick Test verification to ensure accurate restoration

## 🤝 Contribution

I built this to solve a real problem I face daily. The JSON-driven architecture makes it easy to add custom patterns for your specific use cases - no PHP code required!

## 📈 Roadmap

- [ ] Webhook integration for CI/CD pipelines
- [ ] CLI version for terminal workflows
- [ ] Advanced pattern editor UI
- [ ] Community rule library

## 🙏 Acknowledgments

Built for developers who care about security but need AI assistance. Scrubber helps you leverage the power of AI assistants while keeping your sensitive data protected.

---

**🔗 GitHub:** https://github.com/rfvillacacan/scrubber
**📖 Docs:** https://github.com/rfvillacacan/scrubber/blob/main/README.md
**🐳 Docker:** Multi-arch support for Linux, Mac, Windows

**💡 Pro Tip:** Use HTTPS mode for clipboard copy/paste functionality in the browser!

---

#AI #Security #DevTools #OpenSource #Privacy #DevSecOps #Productivity #DeveloperTools #LogAnalysis #CyberSecurity

---

*What's your biggest challenge when sharing logs with AI? Drop a comment below!*

*♻️ Feel free to share if you find this useful!*
