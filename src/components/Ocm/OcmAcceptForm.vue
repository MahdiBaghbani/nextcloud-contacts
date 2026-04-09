<template>
  <div class="ocm_manual_form">
    <h5 class="">
      {{
        t(
          "contacts",
          "Accept an invite from someone outside your organisation to collaborate."
        )
      }}
    </h5>
    <p>
      {{
        t(
          "contacts",
          "After you have accepted the invite, both of you will appear in each others' contacts list and you can start sharing data with each other."
        )
      }}
    </p>

    <div class="ocm_manual_inputs">
      <NcTextField
        v-model="invite"
        :label="t('contacts', 'Invite code or link')"
        type="text"
        :error="Boolean(error)"
        :helper-text="error || t('contacts', 'Paste an invite link, invite code (token@provider), or encoded invite')"
      />

      <div class="ocm_manual_buttons">
        <Button @click="accept">
          <template #icon>
            <IconLoading v-if="loadingUpdate" :size="20" />
            <IconCheck v-else :size="20" />
          </template>
          {{ t("contacts", "Accept") }}
        </Button>
        <Button @click="cancel">
          <template #icon>
            <IconLoading v-if="loadingUpdate" :size="20" />
            <IconCancel v-else :size="20" />
          </template>
          {{ t("contacts", "Cancel") }}
        </Button>
      </div>
    </div>
  </div>
</template>

<script>
import NcTextField from "@nextcloud/vue/components/NcTextField";
import NcButton from "@nextcloud/vue/components/NcButton";

export default {
  name: "OcmAcceptForm",
  components: { NcTextField, NcButton },
  data() {
    return {
      invite: "",
      error: "",
    };
  },
  methods: {
    parseInvite(str) {
      // Try to parse token@provider format
      function tryParseTokenProvider(s) {
        const idx = s.lastIndexOf("@");
        if (idx === -1) return null;
        const token = s.slice(0, idx).trim();
        const provider = s.slice(idx + 1).trim();
        if (!token || !provider) return null;
        return { provider, token };
      }

      // Try to parse as URL with token query parameter
      function tryParseUrl(s) {
        try {
          const url = new URL(s);
          const token = url.searchParams.get('token');
          if (!token) return null;
          // Provider from query param or URL host
          const provider = url.searchParams.get('provider') || url.host;
          if (!provider) return null;
          return { provider, token };
        } catch (e) {
          return null;
        }
      }

      let s = String(str || "").trim();
      
      // 1. Try token@provider format first
      let result = tryParseTokenProvider(s);
      if (result) return result;

      // 2. Try base64 decoding then token@provider
      try {
        const decoded = atob(s);
        result = tryParseTokenProvider(decoded);
        if (result) return result;
      } catch (e) {
        // Not base64, continue
      }

      // 3. Try as URL
      result = tryParseUrl(s);
      if (result) return result;

      throw new Error("Could not parse invite");
    },

    accept() {
      this.error = "";
      try {
        const { provider, token } = this.parseInvite(this.invite);
        this.$emit("accept", { provider, token });
      } catch (e) {
        this.error = t("contacts", "This invite does not look valid. Check that you copied it completely or ask the sender to generate a new one.");
        this.$emit("parse-error", { message: this.error });
      }
    },

    cancel() {
      this.$emit("cancel");
    },
  },
};
</script>
<style lang="scss" scoped>
.ocm_manual_buttons {
  display: flex;
  gap: 0.5rem;
}

.ocm_manual_form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin: 1em;
  p {
    margin-bottom: 1em;
  }

  div.ocm_manual_inputs {
    margin-inline-start: 0.2em;
    width: 80%;
  }
}
</style>
