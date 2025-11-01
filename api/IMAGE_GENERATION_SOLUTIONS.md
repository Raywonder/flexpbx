# FlexPBX Image Generation Solutions - Complete Guide 2025

## 🎯 **Objective**
Create professional promotional images for FlexPBX showcasing all client types (Admin, Desktop, Mobile, Web) with beautiful visual design highlighting desktop server management capabilities.

---

## 🚀 **Option 1: Professional API Solutions**

### **Google Imagen 4 API** (Recommended for Quality)
```bash
# Setup
export GOOGLE_APPLICATION_CREDENTIALS="path/to/credentials.json"
pip install google-cloud-aiplatform

# Usage
curl -X POST \
  -H "Authorization: Bearer $(gcloud auth print-access-token)" \
  -H "Content-Type: application/json" \
  "https://us-central1-aiplatform.googleapis.com/v1/projects/YOUR_PROJECT/locations/us-central1/publishers/google/models/imagen-3.0-generate-001:predict" \
  -d '{
    "instances": [{
      "prompt": "Professional technology promotional image: FlexPBX multi-client server management system. Central glowing server connected to MacBook Pro, Windows desktop, Linux laptop, iPhone, Android tablet, web browsers. Modern flat design, blue gradient background #2196F3 to #1976D2, soft shadows, cyan connection lines. Text: FlexPBX Multi-Client Server Management. Corporate tech aesthetic, minimalist, high quality, 4K."
    }],
    "parameters": {
      "sampleCount": 1
    }
  }'
```

### **OpenAI DALL-E 3 / GPT-Image-1 API**
```python
import openai

client = openai.OpenAI(api_key="your-api-key")

response = client.images.generate(
  model="dall-e-3",
  prompt="Professional FlexPBX server management promotional image. Network topology showing remote server connected to admin clients (desktop computers with crown icons) connected to multiple desktop clients. Tailscale-inspired architecture with clean blue gradient background, modern tech aesthetic, connection flow arrows, text: 'Intelligent Connection Hierarchy'. High quality, corporate style.",
  size="1792x1024",
  quality="hd",
  n=1
)

image_url = response.data[0].url
```

### **Canva API Integration**
```javascript
// Using Canva Connect API
const canvaAPI = 'https://api.canva.com/rest/v1/designs';

const designRequest = {
  design_type: 'A4Document',
  name: 'FlexPBX Promotional Image',
  content: {
    elements: [
      {
        type: 'text',
        content: 'FlexPBX Multi-Client Server Management',
        style: { font_size: 48, color: '#2196F3' }
      }
    ]
  }
};

fetch(canvaAPI, {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_ACCESS_TOKEN',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(designRequest)
});
```

---

## 🐳 **Option 2: Docker-Based Local Solutions**

### **Stable Diffusion WebUI (AUTOMATIC1111)**
```bash
# Pull and run AUTOMATIC1111 WebUI
docker run -d \
  --name sd-webui \
  --gpus all \
  -p 7860:7860 \
  -v sd-models:/app/models \
  -v sd-outputs:/app/outputs \
  -e CLI_ARGS="--listen --port 7860" \
  automaticai/stable-diffusion-webui:latest

# Access at http://localhost:7860
# Prompt: "Professional technology promotional image, FlexPBX multi-client server management system, network diagram, blue gradient background, modern UI design, high quality, 8k"
```

### **ComfyUI Docker Setup**
```bash
# ComfyUI for advanced workflows
docker run -d \
  --name comfyui \
  --gpus all \
  -p 8188:8188 \
  -v comfy-models:/app/models \
  -v comfy-output:/app/output \
  yanwk/comfyui-boot:latest

# Access at http://localhost:8188
# Create node-based workflow for FlexPBX promotional images
```

### **Fooocus - Midjourney-like Experience**
```bash
# Beginner-friendly Docker setup
docker run -d \
  --name fooocus \
  --gpus all \
  -p 7865:7865 \
  -v fooocus-outputs:/app/outputs \
  ghcr.io/lllyasviel/fooocus:latest

# Simple prompt: "FlexPBX server management dashboard, professional tech design"
```

---

## 🛠 **Option 3: Open Source Tools**

### **FLUX.1 Models** (Best Quality Open Source)
```bash
# Install Flux
git clone https://github.com/black-forest-labs/flux
cd flux
pip install -r requirements.txt

# Generate FlexPBX promotional image
python flux_generate.py \
  --prompt "Professional FlexPBX multi-client server management promotional image. Central server connected to multiple client devices (Mac, Windows, Linux, mobile). Blue tech gradient background, modern design, high quality" \
  --model flux-schnell \
  --output flexpbx-promo.png
```

### **LocalAI with Multiple Models**
```bash
# Run LocalAI with image generation
docker run -d \
  --name localai \
  -p 8080:8080 \
  -v $PWD/models:/models \
  quay.io/go-skynet/local-ai:latest

# API call for image generation
curl http://localhost:8080/v1/images/generations \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "FlexPBX promotional image: admin client managing desktop clients in network hierarchy",
    "size": "1024x1024"
  }'
```

---

## 🎨 **Option 4: MCP Server Integration**

### **MiniMax MCP Server**
```bash
# Install MiniMax MCP server
npm install -g @minimax/mcp-server

# Configure in Claude Code
{
  "mcpServers": {
    "minimax": {
      "command": "npx",
      "args": ["@minimax/mcp-server"],
      "env": {
        "MINIMAX_API_KEY": "your-api-key"
      }
    }
  }
}

# Use MCP tool for image generation
# The server provides text-to-speech, image, and video generation APIs
```

### **EverArt MCP Server** (Archived but Functional)
```bash
# Clone archived EverArt MCP
git clone https://github.com/modelcontextprotocol/servers-archived
cd servers-archived/src/everart

# Setup and configure for FlexPBX image generation
npm install
npm start
```

---

## 📋 **Specific FlexPBX Image Generation Prompts**

### **Hero Image Prompt**
```
Professional technology promotional image for FlexPBX Multi-Client Server Management System.

Scene: Clean gradient background (dark blue #1976D2 to light blue #2196F3) with floating, connected devices arranged in intelligent network topology.

Devices: Central glowing server rack (cylindrical, modern) connected to MacBook Pro (showing admin dashboard), Windows desktop (management console), Linux laptop (terminal interface), iPhone (mobile app), Android tablet (call management), browser windows (web interface).

Style: Clean minimalist design, soft shadows, glowing cyan connection lines (#00BCD4), modern flat design with subtle 3D elements, professional technology aesthetic, soft lighting.

Text: "FlexPBX" (large, bold, modern font), "Multi-Client Server Management" (subtitle), "Admin • Desktop • Mobile • Web" (feature badges).

Quality: 4K resolution, corporate branding, high contrast, accessibility compliant colors.
```

### **Architecture Diagram Prompt**
```
Technical network diagram showing FlexPBX connection hierarchy inspired by Tailscale architecture.

Layout: Remote server (cloud icon) → Admin clients (desktop with crown symbols) → Desktop clients (computer icons) → Mobile clients (phone icons).

Visual elements: Clean connection lines with directional arrows, fallback paths shown as dotted lines, status indicators (green/yellow/red), modern technical aesthetic.

Colors: Professional blue gradient background, white connection nodes, green success indicators, subtle grid overlay.

Text: "Intelligent Connection Hierarchy", "Auto-Link • Fallback Connections • Real-time Management".

Style: Clean technical diagram, enterprise software aesthetic, high readability.
```

### **Client Showcase Grid Prompt**
```
Professional grid layout showcasing 6 FlexPBX client types:

1. Admin Client (👨‍💼 icon) - "Primary Management" - macOS/Windows/Linux badges
2. Desktop Client (🖥️ icon) - "Smart Fallback" - Cross-platform tags
3. Mobile App (📱 icon) - "Accessibility Focus" - VoiceOver/TalkBack badges
4. Web Interface (🌐 icon) - "Browser-based" - Chrome/Firefox/Safari icons
5. Legacy Support (🔗 icon) - "Backward Compatible" - Auto-update available
6. Update System (🔄 icon) - "Seamless Updates" - Rollback/Checksum features

Layout: 2x3 grid, each cell with clean white background, colored left border (blue gradient), hover shadows, modern card design.

Background: Subtle tech pattern, professional gradient.
Typography: Modern sans-serif, clear hierarchy, accessibility compliant contrast.
```

---

## 🚀 **Quick Start Implementation**

### **1. Immediate Solution - Use Promotional HTML**
```bash
# Open the created promotional showcase
open promotional-showcase.html

# Take high-quality screenshots
# Use browser dev tools to set specific dimensions (1920x1080, 1200x630, etc.)
```

### **2. API Solution - Google Imagen**
```bash
# Setup Google Cloud
gcloud auth login
gcloud config set project YOUR_PROJECT_ID

# Generate image using the hero prompt above
curl -X POST \
  -H "Authorization: Bearer $(gcloud auth print-access-token)" \
  -H "Content-Type: application/json" \
  "https://us-central1-aiplatform.googleapis.com/v1/projects/YOUR_PROJECT/locations/us-central1/publishers/google/models/imagen-3.0-generate-001:predict" \
  -d @flexpbx_hero_prompt.json
```

### **3. Docker Solution - Stable Diffusion**
```bash
# Quick setup for immediate use
docker run -d --gpus all -p 7860:7860 automaticai/stable-diffusion-webui

# Navigate to http://localhost:7860
# Use the prompts above for generation
```

---

## 🎯 **Integration with FlexPBX Installer**

### **Add Generated Images to Installer**
```html
<!-- Add to install.php welcome section -->
<div class="hero-image">
    <img src="flexpbx-hero-image.png"
         alt="FlexPBX Multi-Client Server Management System showing connected devices in network hierarchy"
         class="responsive-image"
         loading="lazy">
</div>

<!-- Client showcase images -->
<div class="client-gallery">
    <img src="flexpbx-admin-client.png" alt="FlexPBX Admin Client interface">
    <img src="flexpbx-desktop-client.png" alt="FlexPBX Desktop Client connection">
    <img src="flexpbx-mobile-apps.png" alt="FlexPBX Mobile applications">
</div>
```

### **Responsive Image CSS**
```css
.hero-image {
    text-align: center;
    margin: 30px 0;
}

.responsive-image {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
}

.client-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 40px 0;
}

.client-gallery img {
    width: 100%;
    height: auto;
    border-radius: 8px;
    transition: transform 0.3s ease;
}

.client-gallery img:hover {
    transform: scale(1.02);
}
```

---

## 📊 **Recommended Workflow**

1. **Start with HTML Showcase** - Use `promotional-showcase.html` for immediate preview
2. **Generate Hero Image** - Use Google Imagen API with hero prompt
3. **Create Client Images** - Use Stable Diffusion Docker for specific client showcases
4. **Integrate into Installer** - Add responsive images to `install.php`
5. **Optimize for Accessibility** - Ensure alt text and proper contrast
6. **Test Across Devices** - Verify responsive behavior

---

## 💰 **Cost Comparison**

| Solution | Cost | Quality | Setup Time | Control |
|----------|------|---------|------------|---------|
| Google Imagen | $0.01-0.05/image | Excellent | 5 min | Medium |
| DALL-E 3 | $0.02-0.19/image | Excellent | 2 min | Low |
| Stable Diffusion | Free (GPU req.) | Very Good | 30 min | High |
| FLUX.1 | Free | Excellent | 45 min | Very High |
| Canva API | $12-30/month | Good | 15 min | Medium |

**Recommendation**: Start with Google Imagen for immediate high-quality results, then setup Stable Diffusion Docker for ongoing customization needs.