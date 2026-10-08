# 第三方依赖与声明

本项目不打包 vendor 或 node_modules。依赖版本由锁文件确定，安装时应一并保留各依赖的许可证。下列信息来自锁文件；具体条款以安装包中的 LICENSE / NOTICE 为准。

## PHP（包含开发依赖）

| 包 | 版本 | 许可证 |
|---|---|---|
| brick/math | 1.0.0 | MIT |
| ezyang/htmlpurifier | v4.19.1 | LGPL-2.1-or-later |
| firebase/php-jwt | v7.2.1 | BSD-3-Clause |
| guzzlehttp/command | 1.5.4 | MIT |
| guzzlehttp/guzzle | 7.15.5 | MIT |
| guzzlehttp/guzzle-services | 1.7.4 | MIT |
| guzzlehttp/promises | 2.5.3 | MIT |
| guzzlehttp/psr7 | 2.13.1 | MIT |
| guzzlehttp/uri-template | v1.0.11 | MIT |
| intervention/gif | 4.2.4 | MIT |
| intervention/image | 3.11.9 | MIT |
| league/flysystem | 3.36.0 | MIT |
| league/flysystem-local | 3.35.3 | MIT |
| league/mime-type-detection | 1.17.0 | MIT |
| minishlink/web-push | v11.0.0 | MIT |
| php-http/discovery | 1.20.0 | MIT |
| php-http/httplug | 2.4.1 | MIT |
| php-http/promise | 1.3.1 | MIT |
| psr/clock | 1.0.0 | MIT |
| psr/container | 2.0.2 | MIT |
| psr/http-client | 1.0.3 | MIT |
| psr/http-factory | 1.1.0 | MIT |
| psr/http-message | 2.0 | MIT |
| psr/log | 3.0.2 | MIT |
| psr/simple-cache | 3.0.0 | MIT |
| qcloud/cos-sdk-v5 | v2.6.17 | MIT |
| ralouphie/getallheaders | 3.0.3 | MIT |
| spomky-labs/base64url | v2.0.4 | MIT |
| spomky-labs/pki-framework | 1.6.3 | MIT |
| symfony/deprecation-contracts | v3.7.1 | MIT |
| symfony/polyfill-mbstring | v1.38.2 | MIT |
| symfony/polyfill-php80 | v1.43.0 | MIT |
| symfony/polyfill-php83 | v1.41.0 | MIT |
| symfony/var-dumper | v7.4.18 | MIT |
| topthink/framework | v8.1.4 | Apache-2.0 |
| topthink/think-container | v3.0.2 | Apache-2.0 |
| topthink/think-dumper | v1.0.7 | Apache-2.0 |
| topthink/think-filesystem | v3.0.0 | Apache-2.0 |
| topthink/think-helper | v3.1.12 | Apache-2.0 |
| topthink/think-migration | v3.1.1 | Apache-2.0 |
| topthink/think-orm | v4.0.51 | Apache-2.0 |
| topthink/think-trace | v2.0 | Apache-2.0 |
| topthink/think-validate | v3.0.7 | Apache-2.0 |
| web-token/jwt-library | 4.2.3 | MIT |

## 前端直接依赖

| 包 | 版本 | 许可证 |
|---|---|---|
| @iconify-json/tabler | 1.2.41 | MIT |
| @tiptap/extension-color | 2.27.3 | MIT |
| @tiptap/extension-image | 2.27.3 | MIT |
| @tiptap/extension-link | 2.27.3 | MIT |
| @tiptap/extension-placeholder | 2.27.3 | MIT |
| @tiptap/extension-task-item | 2.27.3 | MIT |
| @tiptap/extension-task-list | 2.27.3 | MIT |
| @tiptap/extension-text-style | 2.27.3 | MIT |
| @tiptap/pm | 2.27.3 | MIT |
| @tiptap/starter-kit | 2.27.3 | MIT |
| @tiptap/vue-3 | 2.27.3 | MIT |
| @types/node | 22.20.4 | MIT |
| @vant/auto-import-resolver | 1.3.0 | MIT |
| @vitejs/plugin-vue | 5.2.4 | MIT |
| @vueuse/core | 13.9.0 | MIT |
| axios | 1.20.0 | MIT |
| browser-image-compression | 2.0.2 | MIT |
| dayjs | 1.11.23 | MIT |
| naive-ui | 2.45.3 | MIT |
| pinia | 3.0.4 | MIT |
| typescript | 5.7.3 | Apache-2.0 |
| unocss | 66.10.5 | MIT |
| unplugin-vue-components | 28.8.0 | MIT |
| vant | 4.10.2 | MIT |
| vite | 6.4.3 | MIT |
| vite-plugin-pwa | 0.21.2 | MIT |
| vue | 3.5.43 | MIT |
| vue-router | 4.6.4 | MIT |
| vue-tsc | 2.2.12 | MIT |
| workbox-core | 7.4.1 | MIT |
| workbox-expiration | 7.4.1 | MIT |
| workbox-precaching | 7.4.1 | MIT |
| workbox-routing | 7.4.1 | MIT |
| workbox-strategies | 7.4.1 | MIT |

前端传递依赖的完整清单见 `web/package-lock.json`。UI 图标使用 Tabler（通过 @iconify-json/tabler）；项目应用图标保留在 web/public/icons。Android 外壳使用系统 SDK，不打包第三方 Android 库。

ThinkPHP 应用骨架原始版权说明保留于 `api/LICENSE.txt`；PHP 入口及其他原始声明保留。项目级改动包括自定义配置、路由与业务模块。
