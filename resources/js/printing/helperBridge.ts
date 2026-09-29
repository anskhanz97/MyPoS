export type HelperState =
    "ready" | "unavailable" | "paper_out" | "failed" | "outcome_unknown";

export type SignedHelperAuthorization = {
    algorithm: "Ed25519";
    public_key: string;
    payload: {
        kind: string;
        register_id: string;
        expires_at: string;
        [key: string]: unknown;
    };
    signature: string;
};

export type HelperJob = {
    protocol: 1;
    authorization: SignedHelperAuthorization;
    render: Record<string, unknown>;
};

export type HelperAssessment =
    { accepted: true } | { accepted: false; reason: string };

export function assessHelperJob(
    job: HelperJob,
    registerId: string,
    now = new Date(),
): HelperAssessment {
    if (job.protocol !== 1)
        return { accepted: false, reason: "Unsupported helper protocol." };
    if (job.authorization.algorithm !== "Ed25519")
        return { accepted: false, reason: "Unsupported signature algorithm." };
    if (job.authorization.payload.register_id !== registerId)
        return {
            accepted: false,
            reason: "Authorization belongs to another register.",
        };
    const expiry = Date.parse(job.authorization.payload.expires_at);
    if (!Number.isFinite(expiry) || expiry <= now.getTime())
        return { accepted: false, reason: "Print authorization expired." };
    if (!job.authorization.signature || !job.authorization.public_key)
        return {
            accepted: false,
            reason: "Signed authorization is incomplete.",
        };
    return { accepted: true };
}

export function helperEndpoint(port = 17421): string {
    return `https://127.0.0.1:${port}/mypos/v1/jobs`;
}
