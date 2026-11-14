package com.example.zaylo_mobile

import android.Manifest
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private lateinit var notifications: MethodChannel
    private val channelId = "zaylo_updates"
    private val payloadKey = "zaylo_push_payload"

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        createChannel()
        notifications = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "shop.zaylo/notifications")
        notifications.setMethodCallHandler { call, result ->
            when (call.method) {
                "createChannel" -> { createChannel(); result.success(null) }
                "settings" -> {
                    val settings = if (Build.VERSION.SDK_INT >= 26) {
                        Intent(android.provider.Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(android.provider.Settings.EXTRA_APP_PACKAGE, packageName)
                    } else {
                        Intent(android.provider.Settings.ACTION_APPLICATION_DETAILS_SETTINGS, android.net.Uri.parse("package:$packageName"))
                    }
                    startActivity(settings)
                    result.success(null)
                }
                "clear" -> { getSystemService(NotificationManager::class.java).cancelAll(); result.success(null) }
                "pendingTap" -> {
                    val payload = intent?.getStringExtra(payloadKey)
                    intent?.removeExtra(payloadKey)
                    result.success(payload)
                }
                "show" -> {
                    if (Build.VERSION.SDK_INT < 33 || checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED) {
                        val tag = call.argument<String>("tag") ?: "zaylo"
                        val open = Intent(this, MainActivity::class.java).apply {
                            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
                            putExtra(payloadKey, call.argument<String>("payload"))
                            data = android.net.Uri.parse("zaylo://notification/" + android.net.Uri.encode(tag))
                        }
                        val pending = PendingIntent.getActivity(this, tag.hashCode(), open, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
                        val builder = if (Build.VERSION.SDK_INT >= 26) Notification.Builder(this, channelId) else Notification.Builder(this)
                        val notification = builder.setSmallIcon(R.drawable.ic_stat_zaylo)
                            .setContentTitle(call.argument<String>("title") ?: "ZAYLO")
                            .setContentText(call.argument<String>("body") ?: "You have an update.")
                            .setContentIntent(pending).setAutoCancel(true)
                            .setVisibility(Notification.VISIBILITY_PRIVATE)
                            .setCategory(Notification.CATEGORY_MESSAGE)
                            .setPriority(Notification.PRIORITY_HIGH).build()
                        getSystemService(NotificationManager::class.java).notify(tag, 0, notification)
                    }
                    result.success(null)
                }
                else -> result.notImplemented()
            }
        }
    }

    private fun createChannel() {
        if (Build.VERSION.SDK_INT >= 26) {
            val channel = NotificationChannel(channelId, "ZAYLO updates", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Orders, rider assignments, schedules and messages"
                lockscreenVisibility = Notification.VISIBILITY_PRIVATE
            }
            getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
        }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        intent.getStringExtra(payloadKey)?.let { payload ->
            if (::notifications.isInitialized) {
                notifications.invokeMethod("notificationTapped", payload)
                intent.removeExtra(payloadKey)
            }
        }
    }
}
