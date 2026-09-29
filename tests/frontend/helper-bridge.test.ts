import { describe, expect, it } from "vitest";
import {
    assessHelperJob,
    helperEndpoint,
    type HelperJob,
} from "../../resources/js/printing/helperBridge";

const job: HelperJob = {
    protocol: 1,
    authorization: {
        algorithm: "Ed25519",
        public_key: "public",
        signature: "signature",
        payload: {
            kind: "receipt_print",
            register_id: "register-1",
            expires_at: "2030-01-01T00:00:00Z",
        },
    },
    render: { schema_version: 1 },
};

describe("local print helper boundary", () => {
    it("accepts only current register-scoped signed jobs", () => {
        expect(
            assessHelperJob(job, "register-1", new Date("2029-01-01")),
        ).toEqual({ accepted: true });
        expect(
            assessHelperJob(job, "register-2", new Date("2029-01-01")),
        ).toEqual({
            accepted: false,
            reason: "Authorization belongs to another register.",
        });
        expect(
            assessHelperJob(job, "register-1", new Date("2031-01-01")),
        ).toEqual({ accepted: false, reason: "Print authorization expired." });
    });

    it("uses a loopback TLS endpoint", () => {
        expect(helperEndpoint()).toBe("https://127.0.0.1:17421/mypos/v1/jobs");
    });
});
