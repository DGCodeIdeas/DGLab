# ⚠️ CRITICAL SYSTEM CONSTRAINTS FOR JULES ⚠️

You are operating within the DGLab PWA repository. Before executing any task, you must parse and strictly adhere to `ARCHITECTURE.json`. 

**NON-NEGOTIABLE INFRASTRUCTURE RULES:**
1. **NO RUNTIME COMPOSER:** This application runs on InfinityFree shared hosting. Do not write deployment scripts that rely on `composer install`. All dependencies (if any) must be self-contained or explicitly bypassed.
2. **NO NODE.JS / NPM:** The asset bundling system must be written in 100% native PHP (as defined in the architecture). Do not create `package.json`, do not use Webpack, Vite, or Tailwind CLI.
3. **MEMORY & TIMEOUT LIMITS:** InfinityFree will kill processes taking longer than 30 seconds or exceeding memory limits. All file processing MUST use the `ChunkProcessor` pattern defined in the architecture.
4. **VANILLA PHP 8+ MVC:** Use modern PHP 8.0+ features (constructor property promotion, match expressions, typed properties). Do NOT pull in Laravel, Symfony, or any external framework components. We are building the custom front-controller architecture defined in the JSON.
5. **NO SSH/CLI ASSUMPTIONS:** Do not write code that assumes the user has terminal access to the production server.

When opening a Pull Request, your plan must explicitly state how your code complies with the InfinityFree hosting constraints.