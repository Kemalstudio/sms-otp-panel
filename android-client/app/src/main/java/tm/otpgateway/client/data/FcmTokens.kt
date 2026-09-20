package tm.otpgateway.client.data

import android.util.Log
import com.google.firebase.messaging.FirebaseMessaging
import kotlinx.coroutines.suspendCancellableCoroutine
import tm.otpgateway.client.BuildConfig
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException

/**
 * This installation's push address.
 *
 * Firebase caches the token on disk, so asking for it on every heartbeat costs
 * nothing until it actually rotates.
 */
object FcmTokens {

    private const val TAG = "FcmTokens"

    /**
     * Throws if Firebase cannot produce a token — pairing must not proceed
     * without one.
     *
     * Messaging 25.x renamed this flow to `register()` + the service callback
     * `onRegistered()`, which only ever pushes a token at us. Pairing and the
     * heartbeat both need to ask for the current one on demand, and `getToken()`
     * is still the only API that answers that question.
     */
    @Suppress("DEPRECATION")
    suspend fun require(): String = suspendCancellableCoroutine { continuation ->
        FirebaseMessaging.getInstance().token
            .addOnSuccessListener { token ->
                if (continuation.isActive) continuation.resume(token)
            }
            .addOnFailureListener { e ->
                if (continuation.isActive) continuation.resumeWithException(e)
            }
    }

    /** Null instead of throwing, for callers that can carry on without it. */
    suspend fun currentOrNull(): String? {
        if (!BuildConfig.HAS_FIREBASE_CONFIG) return null

        return runCatching { require() }
            .onFailure { Log.w(TAG, "could not read the FCM token", it) }
            .getOrNull()
    }
}
