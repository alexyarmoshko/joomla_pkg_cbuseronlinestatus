# Makefile for pkg_cbuseronlinestatus — Joomla package
#
# Delegates to sub-Makefiles for the plugin and module, then builds the
# package ZIP and patches the package update XML.
#
# Usage:
#   make info    — show package metadata
#   make dist    — build all ZIPs and update manifests
#   make clean   — remove all built ZIPs
#
# @author      Yak Shaver <me@kayakshaver.com>
# @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
# @license     GNU General Public License version 2 or later; see LICENSE.txt

NAME         := cbuseronlinestatus
PLG_DIR      := plg_system_$(NAME)
MOD_DIR      := mod_$(NAME)
PKG_NAME     := pkg_$(NAME)
PKG_MANIFEST := $(PKG_NAME).xml
PKG_UPDATE   := $(PKG_NAME).update.xml
INST_DIR     := installation

VERSION      := $(shell awk -F'[<>]' '/<version>/{print $$3; exit}' "$(PKG_MANIFEST)")
PLG_ZIP      := plg_system_$(NAME)-$(VERSION).zip
MOD_ZIP      := mod_$(NAME)-$(VERSION).zip
PKG_ZIP      := $(PKG_NAME)-$(VERSION).zip

GITHUB_OWNER ?= alexyarmoshko
GITHUB_REPO  ?= joomla_pkg_cbuseronlinestatus

.PHONY: info dist clean

info:
	@echo "Package: $(PKG_NAME)"
	@echo "Version: $(VERSION)"
	@echo "Plugin:  $(INST_DIR)/$(PLG_ZIP)"
	@echo "Module:  $(INST_DIR)/$(MOD_ZIP)"
	@echo "Package: $(INST_DIR)/$(PKG_ZIP)"

dist: clean
	@$(MAKE) -C $(PLG_DIR) dist
	@$(MAKE) -C $(MOD_DIR) dist

	@echo "--- Building package ZIP ---"
	cp $(PKG_MANIFEST) $(PKG_MANIFEST).bak
	sed -i 's/plg_system_$(NAME)\.zip/$(PLG_ZIP)/g; s/mod_$(NAME)\.zip/$(MOD_ZIP)/g' $(PKG_MANIFEST)
	zip -r -X $(INST_DIR)/$(PKG_ZIP) \
		$(PKG_MANIFEST) \
		$(INST_DIR)/$(PLG_ZIP) \
		$(INST_DIR)/$(MOD_ZIP) \
		language/ \
		LICENSE
	mv $(PKG_MANIFEST).bak $(PKG_MANIFEST)

	@echo "--- Updating package update XML ---"
	@SHA256="$$( (command -v sha256sum >/dev/null && sha256sum "$(INST_DIR)/$(PKG_ZIP)" || shasum -a 256 "$(INST_DIR)/$(PKG_ZIP)") | awk '{print $$1}' )"; \
	echo "Package SHA256: $$SHA256"; \
	awk -v version="$(VERSION)" \
	    -v url="https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/v$(VERSION)/$(PKG_ZIP)" \
	    -v sha="$$SHA256" '{ \
		if ($$0 ~ /<version>[^<]+<\/version>/) { \
			sub(/<version>[^<]+<\/version>/, "<version>" version "</version>"); \
		} else if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
			sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
		} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
			sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
		} \
		print; \
	}' "$(PKG_UPDATE)" > "$(PKG_UPDATE).tmp" && mv "$(PKG_UPDATE).tmp" "$(PKG_UPDATE)"

	@echo ""
	@echo "=== Build complete ==="
	@echo "  $(INST_DIR)/$(PLG_ZIP)"
	@echo "  $(INST_DIR)/$(MOD_ZIP)"
	@echo "  $(INST_DIR)/$(PKG_ZIP)"

clean:
	@$(MAKE) -C $(PLG_DIR) clean
	@$(MAKE) -C $(MOD_DIR) clean
	@rm -f $(INST_DIR)/$(PKG_ZIP)
