FROM node:24

LABEL "au.pixelated.container.function"="To run Claude Code inside of a sandbox" \
  "au.pixelated.container.keywords"="claude code,sandbox,node,bun"

ARG TZ
ENV TZ="$TZ"

ARG CLAUDE_CODE_VERSION=latest

# Install basic development tools and iptables/ipset
RUN apt-get update && apt-get install -y --no-install-recommends \
  less \
  git \
  procps \
  sudo \
  fzf \
  zsh \
  man-db \
  unzip \
  gnupg2 \
  ca-certificates \
  locales \
  iptables \
  ipset \
  iproute2 \
  socat \
  conntrack \
  dnsutils \
  aggregate \
  jq \
  nano \
  vim \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

# Debian ships no generated locales, so any client forwarding LC_ALL/LANG (VS Code,
# `devcontainer exec`, ssh) makes every shell open with a setlocale warning.
RUN sed -i 's/^# *\(en_US.UTF-8\|en_AU.UTF-8\|en_GB.UTF-8\)/\1/' /etc/locale.gen \
  && locale-gen
ENV LANG=C.UTF-8

# GitHub CLI from the official repo — Debian bookworm ships 2.23.0, which is old
# enough to be missing subcommands Claude reaches for.
RUN mkdir -p -m 755 /etc/apt/keyrings \
  && wget -qO- https://cli.github.com/packages/githubcli-archive-keyring.gpg \
     > /etc/apt/keyrings/githubcli-archive-keyring.gpg \
  && chmod go+r /etc/apt/keyrings/githubcli-archive-keyring.gpg \
  && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" \
     > /etc/apt/sources.list.d/github-cli.list \
  && apt-get update && apt-get install -y --no-install-recommends gh \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

# Ensure default node user has access to /usr/local/share
RUN mkdir -p /usr/local/share/npm-global && \
  chown -R node:node /usr/local/share

ARG USERNAME=node

# Persist bash history.
RUN SNIPPET="export PROMPT_COMMAND='history -a' && export HISTFILE=/commandhistory/.bash_history" \
  && mkdir /commandhistory \
  && touch /commandhistory/.bash_history \
  && chown -R $USERNAME /commandhistory

# Set `DEVCONTAINER` environment variable to help with orientation
ENV DEVCONTAINER=true

# Create workspace and config directories and set permissions.
#
# Only the /workspaces parent is created here: the project itself is bind-mounted
# into a per-project subfolder named at template-apply time, so the image cannot
# know the leaf name.
#
# .config must exist and be owned by node before the named volume is mounted over
# it. Docker creates a missing mountpoint as root:root, and the volume it seeds
# from an absent directory is empty — so without this, ~/.config comes up
# unwritable by the only user that ever runs anything here.
RUN mkdir -p /workspaces /home/node/.claude /home/node/.config && \
  chown -R node:node /workspaces /home/node/.claude /home/node/.config

WORKDIR /workspaces

ARG GIT_DELTA_VERSION=0.19.2
RUN ARCH=$(dpkg --print-architecture) && \
  wget "https://github.com/dandavison/delta/releases/download/${GIT_DELTA_VERSION}/git-delta_${GIT_DELTA_VERSION}_${ARCH}.deb" && \
  dpkg -i "git-delta_${GIT_DELTA_VERSION}_${ARCH}.deb" && \
  rm "git-delta_${GIT_DELTA_VERSION}_${ARCH}.deb"

# Set up non-root user
USER node

# Install global packages
ENV NPM_CONFIG_PREFIX=/usr/local/share/npm-global
ENV PATH=$PATH:/usr/local/share/npm-global/bin

# Set the default shell to zsh rather than sh
ENV SHELL=/bin/zsh

# Set the default editor and visual
ENV EDITOR=nano
ENV VISUAL=nano

# Default powerline10k theme
ARG ZSH_IN_DOCKER_VERSION=1.2.1
RUN sh -c "$(wget -O- https://github.com/deluan/zsh-in-docker/releases/download/v${ZSH_IN_DOCKER_VERSION}/zsh-in-docker.sh)" -- \
  -p git \
  -p fzf \
  -a "source /usr/share/doc/fzf/examples/key-bindings.zsh" \
  -a "source /usr/share/doc/fzf/examples/completion.zsh" \
  -a "export PROMPT_COMMAND='history -a' && export HISTFILE=/commandhistory/.bash_history" \
  -x

# Install Claude
RUN npm install -g @anthropic-ai/claude-code@${CLAUDE_CODE_VERSION}

# Install Bun. Installed per-user rather than globally, so it lands in
# /home/node/.bun and `bun upgrade` works without root.
ENV BUN_INSTALL=/home/node/.bun
ENV PATH=/home/node/.bun/bin:$PATH
RUN curl -fsSL https://bun.com/install | bash

# Copy and set up firewall script.
#
# The whitelist is baked in as a fallback; devcontainer.json normally bind-mounts
# /etc/firewall over the top so it can be edited without a rebuild.
#
# The sudoers rule is deliberately narrow: `node` may run configure-firewall.sh as
# root and nothing else, so a compromised agent can't sudo its way out.
USER root
COPY configure-firewall.sh /usr/local/bin/
COPY host-port-relay.sh /usr/local/bin/
COPY firewall/firewall-whitelist-domains.json /etc/firewall/
COPY firewall-warning.sh /etc/firewall-warning.sh
RUN chmod +x /usr/local/bin/configure-firewall.sh /usr/local/bin/host-port-relay.sh && \
    chmod 644 /etc/firewall/firewall-whitelist-domains.json /etc/firewall-warning.sh && \
    echo "node ALL=(root) NOPASSWD: /usr/local/bin/configure-firewall.sh" > /etc/sudoers.d/node-firewall && \
    chmod 0440 /etc/sudoers.d/node-firewall

# Warn on every interactive shell when the firewall is not actually up. Both rc
# files are needed: zsh does not read /etc/bash.bashrc, and the default shell here
# is zsh while `devcontainer exec` and CI use bash.
RUN mkdir -p /etc/zsh && \
    for rc in /etc/bash.bashrc /etc/zsh/zshrc; do \
      printf '\n%s\n' 'if [ -f /etc/firewall-warning.sh ]; then . /etc/firewall-warning.sh; fi' >> "$rc"; \
    done
USER node
