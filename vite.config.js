import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
// import inject from "@rollup/plugin-inject";

export default defineConfig({
  plugins: [
    laravel({
      detectTls: "snipe-it.test",
      input: [
        "resources/assets/less/init.less",
        // 'resources/js/app.js'
      ],
      refresh: true,
    }),
    // inject({
    //   jQuery: "jquery",
    // }),
  ],
});
