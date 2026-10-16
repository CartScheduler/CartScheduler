import { spawn } from "node:child_process";
import type { Logger, Plugin, ResolvedConfig } from "vite";

export interface PhpGenerateOptions {
  /** Executable to run. @default 'php' */
  command?: string;
  /** Arguments passed to the executable. @default ['artisan', 'typescript:transform'] */
  args: string[];
  /** Path fragments that trigger a re-run. @default ['/Data/', '/Enums/'] */
  watch: string[];
  /** Run once when the dev server / build starts. @default true */
  runOnStart?: boolean;
  /** Debounce delay in ms. @default 150 */
  debounce?: number;
}

/**
 * Runs a PHP command (e.g. an artisan generator) on startup and whenever
 * a PHP file in a watched directory changes during `vite serve`.
 */
export default function runCommand(options: PhpGenerateOptions): Plugin {
  if (!options.args || !options.watch) {
    throw new Error("args, and watch are required");
  }

  const { command = "php", args, watch, runOnStart = false, debounce = 150 } = options;

  let isServe = false;
  let root: string = process.cwd();
  let logger: Pick<Logger, "info" | "error"> = console;

  let running = false;
  let queued = false;
  let timer: ReturnType<typeof setTimeout> | undefined;

  function run(): void {
    if (running) {
      // A run is already in progress; run once more when it finishes.
      queued = true;
      return;
    }

    running = true;
    logger.info(`[php-tool-runner] running: ${command} ${args.join(" ")}`);

    const child = spawn(command, args, {
      cwd: root,
      stdio: ["ignore", "pipe", "pipe"],
      shell: process.platform === "win32",
    });

    let output = "";
    child.stdout?.on("data", (chunk: Buffer) => (output += chunk));
    child.stderr?.on("data", (chunk: Buffer) => (output += chunk));

    child.on("error", (error: Error) => {
      running = false;
      logger.error(`[php-tool-runner] failed to start: ${error.message}`);
    });

    child.on("close", (code: number | null) => {
      running = false;

      if (code === 0) {
        logger.info("[php-tool-runner] done");
      } else {
        logger.error(`[php-tool-runner] exited with code ${code}\n${output.trim()}`);
      }

      if (queued) {
        queued = false;
        run();
      }
    });
  }

  function generate(): void {
    clearTimeout(timer);
    timer = setTimeout(run, debounce);
  }

  return {
    name: "vite-plugin-php-tool-runner",

    configResolved(config: ResolvedConfig) {
      isServe = config.command === "serve";
      root = config.root;
      logger = config.logger;
    },

    buildStart() {
      if (runOnStart) {
        run();
      }
    },

    handleHotUpdate({ file }) {
      if (isServe && file.endsWith(".php") && watch.some((fragment) => file.includes(fragment))) {
        generate();
      }
    },
  };
}
