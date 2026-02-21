# Makefile for pkg_cbuseronlinestatus
# Builds plugin, module, and package ZIPs with update XML SHA256 patching.

NAME         := cbuseronlinestatus
PLG_NAME     := plg_system_$(NAME)
MOD_NAME     := mod_$(NAME)
PKG_NAME     := pkg_$(NAME)

# Read version from the package manifest
VERSION      := $(shell grep -oP '(?<=<version>)[^<]+' $(PKG_NAME).xml)

PLG_DIR      := $(PLG_NAME)
MOD_DIR      := $(MOD_NAME)

PLG_ZIP      := $(PLG_NAME)-$(VERSION).zip
MOD_ZIP      := $(MOD_NAME)-$(VERSION).zip
PKG_ZIP      := $(PKG_NAME)-$(VERSION).zip

INST_DIR     := installation

.PHONY: info dist clean

info:
	@echo "Name:    $(PKG_NAME)"
	@echo "Version: $(VERSION)"
	@echo "Plugin:  $(PLG_ZIP)"
	@echo "Module:  $(MOD_ZIP)"
	@echo "Package: $(PKG_ZIP)"

dist: clean
	@mkdir -p $(INST_DIR)

	@echo "--- Building plugin ZIP ---"
	cp LICENSE $(PLG_DIR)/LICENSE
	cd $(PLG_DIR) && zip -r ../$(INST_DIR)/$(PLG_ZIP) . -x ".*"
	rm $(PLG_DIR)/LICENSE

	@echo "--- Building module ZIP ---"
	cp LICENSE $(MOD_DIR)/LICENSE
	cd $(MOD_DIR) && zip -r ../$(INST_DIR)/$(MOD_ZIP) . -x ".*"
	rm $(MOD_DIR)/LICENSE

	@echo "--- Calculating SHA256 hashes ---"
	$(eval PLG_SHA256 := $(shell sha256sum $(INST_DIR)/$(PLG_ZIP) | cut -d' ' -f1))
	$(eval MOD_SHA256 := $(shell sha256sum $(INST_DIR)/$(MOD_ZIP) | cut -d' ' -f1))

	@echo "Plugin SHA256:  $(PLG_SHA256)"
	@echo "Module SHA256:  $(MOD_SHA256)"

	@echo "--- Updating plugin update XML with SHA256 ---"
	sed -i 's/__SHA256_PLG__/$(PLG_SHA256)/g' $(PLG_NAME).update.xml

	@echo "--- Updating module update XML with SHA256 ---"
	sed -i 's/__SHA256_MOD__/$(MOD_SHA256)/g' $(MOD_NAME).update.xml

	@echo "--- Building package ZIP ---"
	cp $(PKG_NAME).xml $(PKG_NAME).xml.bak
	sed -i 's/$(PLG_NAME)\.zip/$(PLG_ZIP)/g; s/$(MOD_NAME)\.zip/$(MOD_ZIP)/g' $(PKG_NAME).xml
	zip -r $(INST_DIR)/$(PKG_ZIP) \
		$(PKG_NAME).xml \
		$(INST_DIR)/$(PLG_ZIP) \
		$(INST_DIR)/$(MOD_ZIP) \
		language/ \
		LICENSE
	mv $(PKG_NAME).xml.bak $(PKG_NAME).xml

	@echo "--- Calculating package SHA256 ---"
	$(eval PKG_SHA256 := $(shell sha256sum $(INST_DIR)/$(PKG_ZIP) | cut -d' ' -f1))
	@echo "Package SHA256: $(PKG_SHA256)"

	@echo "--- Updating package update XML with SHA256 ---"
	sed -i 's/__SHA256_PKG__/$(PKG_SHA256)/g' $(PKG_NAME).update.xml

	@echo ""
	@echo "=== Build complete ==="
	@echo "  $(INST_DIR)/$(PLG_ZIP)"
	@echo "  $(INST_DIR)/$(MOD_ZIP)"
	@echo "  $(INST_DIR)/$(PKG_ZIP)"

clean:
	rm -f $(INST_DIR)/$(PLG_ZIP) $(INST_DIR)/$(MOD_ZIP) $(INST_DIR)/$(PKG_ZIP)
