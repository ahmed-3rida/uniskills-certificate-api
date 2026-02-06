# 🚀 Uniskills Certificate API - Vercel Deployment

## Quick Start

### 1. Install Vercel CLI
```bash
npm install -g vercel
```

### 2. Login
```bash
vercel login
```

### 3. Deploy
```bash
vercel
```

### 4. Your API will be available at:
```
https://your-project-name.vercel.app/api/certificate
```

---

## Testing

```bash
curl -X POST https://your-project-name.vercel.app/api/certificate \
  -H "Content-Type: application/json" \
  -d '{
    "studentName": "أحمد محمد",
    "courseName": "تطوير تطبيقات Flutter",
    "instructorName": "د. محمد علي",
    "date": "15 يناير 2026",
    "language": "ar"
  }'
```

---

## Files Structure

```
vercel-deploy/
├── api/
│   └── certificate.php      # Main API endpoint
├── templates/
│   ├── ar.jpg               # Arabic certificate template
│   └── en.jpg               # English certificate template
├── fonts/
│   └── Cairo-Bold.ttf       # Arabic font
├── arabic_glyphs.php        # Arabic text shaping library
├── config.php               # Configuration
├── vercel.json              # Vercel configuration
└── README.md                # This file
```

---

## Configuration

Edit `config.php` to adjust text positions for Arabic and English certificates.

---

## Support

For issues, check:
- Vercel logs: `vercel logs`
- Vercel docs: https://vercel.com/docs

---

**Ready to deploy! 🎉**
# uniskills-certificate-api
