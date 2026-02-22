# Makefile for mod_cbuseronlinestatus — Joomla site module
#
# Build the installable ZIP into installation/.
# Usage:
#   make info    — show module metadata
#   make dist    — build ZIP and update manifest (if update XML exists)
#   make clean   — remove the built ZIP
#
# @author      Yak Shaver <me@kayakshaver.com>
# @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
# @license     GNU General Public License version 2 or later; see LICENSE.txt

MODULE_NAME := cbuseronlinestatus
MODULE_MANIFEST_XML := mod_$(MODULE_NAME).xml
MODULE_UPDATE_XML := mod_$(MODULE_NAME).update.xml
INSTALL_DIR ?= ../installation
LICENSE_FILE ?= ../LICENSE

MODULE_VERSION := $(shell awk -F'[<>]' '/<version>/{print $$3; exit}' "$(MODULE_MANIFEST_XML)")
ZIP_NAME := mod_$(MODULE_NAME)-$(MODULE_VERSION).zip
ZIP_PATH := $(INSTALL_DIR)/$(ZIP_NAME)

GITHUB_OWNER ?= alexyarmoshko
GITHUB_REPO ?= joomla_pkg_cbuseronlinestatus
GITHUB_REF ?= v$(MODULE_VERSION)

MODULE_FILES := $(MODULE_MANIFEST_XML) services/ src/ tmpl/ language/ index.html

.PHONY: info dist clean

info:
	@echo "Module:          mod_$(MODULE_NAME)"
	@echo "Module version:  $(MODULE_VERSION)"
	@echo "Source:          $(MODULE_FILES)"
	@echo "Package output:  $(ZIP_PATH)"
	@echo "Manifest output: $(MODULE_UPDATE_XML)"

dist: $(ZIP_PATH)
	@SHA256="$$( (command -v sha256sum >/dev/null && sha256sum "$(ZIP_PATH)" || shasum -a 256 "$(ZIP_PATH)") | awk '{print $$1}' )"; \
	if [ -f "$(MODULE_UPDATE_XML)" ]; then \
		DOWNLOAD_URL="https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/$(GITHUB_REF)/$(ZIP_NAME)"; \
		awk -v version="$(MODULE_VERSION)" -v url="$$DOWNLOAD_URL" -v sha="$$SHA256" '{ \
			if ($$0 ~ /<version>[^<]+<\/version>/) { \
				sub(/<version>[^<]+<\/version>/, "<version>" version "</version>"); \
			} else if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
				sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
			} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
				sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
			} \
			print; \
		}' "$(MODULE_UPDATE_XML)" > "$(MODULE_UPDATE_XML).tmp" && mv "$(MODULE_UPDATE_XML).tmp" "$(MODULE_UPDATE_XML)"; \
		echo "Updated $(MODULE_UPDATE_XML)"; \
	else \
		echo "Skipped manifest update; $(MODULE_UPDATE_XML) not found"; \
	fi

$(ZIP_PATH):
	@mkdir -p "$(INSTALL_DIR)"
	@rm -f "$(ZIP_PATH)"
	@zip -qr -X "$(ZIP_PATH)" $(MODULE_FILES) -x "*.DS_Store" -x "*/.DS_Store"
	@zip -qj -X "$(ZIP_PATH)" "$(LICENSE_FILE)"
	@echo "Built $(ZIP_PATH)"

clean:
	@rm -f "$(ZIP_PATH)"
