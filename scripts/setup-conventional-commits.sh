#!/bin/bash

# Setup script for Conventional Commits
# This script configures git hooks and tools for conventional commits

set -e

echo "🚀 Setting up Conventional Commits for Astero..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Check if we're in a git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    echo -e "${RED}❌ Error: Not in a git repository${NC}"
    exit 1
fi

echo -e "${BLUE}📝 Setting up git commit template...${NC}"
git config commit.template .gitmessage
echo -e "${GREEN}✅ Git commit template configured${NC}"

# Check if Node.js is available
if ! command -v node &> /dev/null; then
    echo -e "${YELLOW}⚠️  Node.js not found. Please install Node.js to use commitlint.${NC}"
    echo -e "${YELLOW}   You can still use the commit template manually.${NC}"
    exit 0
fi

# Check if npm is available
if ! command -v npm &> /dev/null; then
    echo -e "${YELLOW}⚠️  npm not found. Please install npm to use commitlint.${NC}"
    echo -e "${YELLOW}   You can still use the commit template manually.${NC}"
    exit 0
fi

echo -e "${BLUE}📦 Installing commitlint...${NC}"

# Install commitlint globally if not already installed
if ! command -v commitlint &> /dev/null; then
    echo -e "${YELLOW}Installing @commitlint/cli and @commitlint/config-conventional globally...${NC}"
    npm install -g @commitlint/cli @commitlint/config-conventional
else
    echo -e "${GREEN}✅ commitlint already installed${NC}"
fi

# Copy commitlint config to project root
echo -e "${BLUE}📋 Setting up commitlint configuration...${NC}"
if [ ! -f "commitlint.config.js" ]; then
    cp .github/commitlint.config.js commitlint.config.js
    echo -e "${GREEN}✅ commitlint.config.js created${NC}"
else
    echo -e "${YELLOW}⚠️  commitlint.config.js already exists, skipping...${NC}"
fi

# Set up git hooks
echo -e "${BLUE}🪝 Setting up git hooks...${NC}"

# Create hooks directory if it doesn't exist
mkdir -p .git/hooks

# Create commit-msg hook
COMMIT_MSG_HOOK=".git/hooks/commit-msg"
cat > "$COMMIT_MSG_HOOK" << 'EOF'
#!/bin/sh

# Validate commit message using commitlint
if command -v npx &> /dev/null; then
    npx --no-install commitlint --edit "$1"
elif command -v commitlint &> /dev/null; then
    commitlint --edit "$1"
else
    echo "⚠️  commitlint not found. Commit message validation skipped."
    echo "   Run 'npm install -g @commitlint/cli @commitlint/config-conventional' to enable validation."
fi
EOF

# Make the hook executable
chmod +x "$COMMIT_MSG_HOOK"
echo -e "${GREEN}✅ commit-msg hook created${NC}"

# Create prepare-commit-msg hook for better commit template
PREPARE_COMMIT_MSG_HOOK=".git/hooks/prepare-commit-msg"
cat > "$PREPARE_COMMIT_MSG_HOOK" << 'EOF'
#!/bin/sh

# This hook is called by git commit right after preparing the default log message,
# and right before the editor is started.

COMMIT_MSG_FILE=$1
COMMIT_SOURCE=$2
SHA1=$3

# Only add template for regular commits (not merges, rebases, etc.)
if [ "$COMMIT_SOURCE" = "" ]; then
    # Check if the commit message is empty or just contains comments
    if ! grep -qE '^[^#]' "$COMMIT_MSG_FILE"; then
        # Add a helpful template at the top
        cat > "$COMMIT_MSG_FILE" << 'TEMPLATE'
# <type>[optional scope]: <description>
#
# Example: feat(auth): add OAuth2 login support
#
# Types: feat, fix, docs, style, refactor, perf, test, chore, ci, build, security
# Scopes: auth, api, web, admin, database, models, controllers, etc.
#
# Remember:
# - Use imperative mood ("add" not "added" or "adds")
# - Don't capitalize first letter of description
# - No period at the end of description
# - Include BREAKING CHANGE: in footer for breaking changes
#
# More examples:
# fix(api): handle null response from external service
# docs: update installation instructions
# perf(database): optimize user lookup queries
#
# Breaking change example:
# feat(api): redesign authentication system
#
# BREAKING CHANGE: remove deprecated /api/v1/auth endpoint

TEMPLATE
    fi
fi
EOF

chmod +x "$PREPARE_COMMIT_MSG_HOOK"
echo -e "${GREEN}✅ prepare-commit-msg hook created${NC}"

# Optional: Install husky for better hook management
echo -e "${BLUE}🐕 Would you like to install Husky for better git hook management? (y/N)${NC}"
read -r response
if [[ "$response" =~ ^([yY][eE][sS]|[yY])$ ]]; then
    if [ -f "package.json" ]; then
        echo -e "${YELLOW}Installing husky...${NC}"
        npm install --save-dev husky
        npx husky install
        npx husky add .husky/commit-msg 'npx --no-install commitlint --edit "$1"'
        echo -e "${GREEN}✅ Husky installed and configured${NC}"
    else
        echo -e "${YELLOW}⚠️  No package.json found. Skipping Husky installation.${NC}"
    fi
fi

echo -e "${GREEN}🎉 Conventional Commits setup complete!${NC}"
echo -e "${BLUE}📚 Quick reference:${NC}"
echo -e "  • ${YELLOW}feat(scope): add new feature${NC}"
echo -e "  • ${YELLOW}fix(scope): fix a bug${NC}"
echo -e "  • ${YELLOW}docs: update documentation${NC}"
echo -e "  • ${YELLOW}style: format code${NC}"
echo -e "  • ${YELLOW}refactor: refactor code${NC}"
echo -e "  • ${YELLOW}perf: improve performance${NC}"
echo -e "  • ${YELLOW}test: add tests${NC}"
echo -e "  • ${YELLOW}chore: maintenance tasks${NC}"
echo ""
echo -e "${BLUE}💡 Tips:${NC}"
echo -e "  • Use 'git commit' to see the template"
echo -e "  • Commit messages will be automatically validated"
echo -e "  • Check .gitmessage for more examples"
echo -e "  • CHANGELOG.md will be automatically generated from your commits"
echo ""
echo -e "${GREEN}Happy committing! 🚀${NC}"
