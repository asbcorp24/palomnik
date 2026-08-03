import 'package:flutter/material.dart';

/// Network image that opens a native full-screen, pinch-to-zoom viewer.
class ZoomableNetworkImage extends StatelessWidget {
  const ZoomableNetworkImage({
    super.key,
    required this.url,
    this.caption,
    this.height,
    this.width,
    this.fit = BoxFit.cover,
    this.borderRadius,
    this.errorBuilder,
    this.showZoomBadge = true,
  });

  final String url;
  final String? caption;
  final double? height;
  final double? width;
  final BoxFit fit;
  final BorderRadius? borderRadius;
  final ImageErrorWidgetBuilder? errorBuilder;
  final bool showZoomBadge;

  @override
  Widget build(BuildContext context) {
    final image = Image.network(
      url,
      height: height,
      width: width,
      fit: fit,
      loadingBuilder: (context, child, progress) {
        if (progress == null) return child;
        return SizedBox(
          height: height,
          width: width,
          child: const ColoredBox(
            color: Color(0xFFF2EEE7),
            child: Center(child: CircularProgressIndicator()),
          ),
        );
      },
      errorBuilder: errorBuilder,
    );

    return Semantics(
      button: true,
      label: caption == null || caption!.trim().isEmpty
          ? 'Увеличить фотографию'
          : 'Увеличить фотографию: $caption',
      child: Tooltip(
        message: 'Нажмите, чтобы увеличить',
        child: GestureDetector(
          behavior: HitTestBehavior.opaque,
          onTap: () => showZoomablePhoto(
            context,
            url: url,
            caption: caption,
          ),
          child: ClipRRect(
            borderRadius: borderRadius ?? BorderRadius.zero,
            child: Stack(
              fit: StackFit.passthrough,
              children: [
                image,
                if (showZoomBadge)
                  const Positioned(
                    right: 10,
                    bottom: 10,
                    child: _ZoomBadge(),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

Future<void> showZoomablePhoto(
  BuildContext context, {
  required String url,
  String? caption,
}) {
  return showGeneralDialog<void>(
    context: context,
    barrierDismissible: true,
    barrierLabel: 'Закрыть увеличенную фотографию',
    barrierColor: Colors.black.withValues(alpha: .94),
    transitionDuration: const Duration(milliseconds: 180),
    pageBuilder: (context, animation, secondaryAnimation) => _PhotoViewer(
      url: url,
      caption: caption,
    ),
    transitionBuilder: (context, animation, secondaryAnimation, child) {
      final curved = CurvedAnimation(
        parent: animation,
        curve: Curves.easeOutCubic,
        reverseCurve: Curves.easeInCubic,
      );
      return FadeTransition(
        opacity: curved,
        child: ScaleTransition(
          scale: Tween<double>(begin: .985, end: 1).animate(curved),
          child: child,
        ),
      );
    },
  );
}

class _PhotoViewer extends StatefulWidget {
  const _PhotoViewer({required this.url, this.caption});

  final String url;
  final String? caption;

  @override
  State<_PhotoViewer> createState() => _PhotoViewerState();
}

class _PhotoViewerState extends State<_PhotoViewer> {
  final TransformationController _transformation = TransformationController();
  TapDownDetails? _doubleTapDetails;

  @override
  void dispose() {
    _transformation.dispose();
    super.dispose();
  }

  void _toggleZoom() {
    final position = _doubleTapDetails?.localPosition;
    final isZoomed = _transformation.value.getMaxScaleOnAxis() > 1.05;

    if (isZoomed || position == null) {
      _transformation.value = Matrix4.identity();
      return;
    }

    const scale = 2.5;
    _transformation.value = Matrix4.identity()
      ..translateByDouble(
        -position.dx * (scale - 1),
        -position.dy * (scale - 1),
        0,
        1,
      )
      ..scaleByDouble(scale, scale, scale, 1);
  }

  @override
  Widget build(BuildContext context) {
    final caption = widget.caption?.trim();

    return Material(
      color: Colors.transparent,
      child: SafeArea(
        child: Stack(
          children: [
            Positioned.fill(
              child: GestureDetector(
                onDoubleTapDown: (details) => _doubleTapDetails = details,
                onDoubleTap: _toggleZoom,
                child: InteractiveViewer(
                  transformationController: _transformation,
                  minScale: 1,
                  maxScale: 5,
                  boundaryMargin: const EdgeInsets.all(80),
                  clipBehavior: Clip.none,
                  child: Center(
                    child: Image.network(
                      widget.url,
                      fit: BoxFit.contain,
                      loadingBuilder: (context, child, progress) {
                        if (progress == null) return child;
                        final total = progress.expectedTotalBytes;
                        final value = total == null
                            ? null
                            : progress.cumulativeBytesLoaded / total;
                        return Center(
                          child: CircularProgressIndicator(value: value),
                        );
                      },
                      errorBuilder: (context, error, stackTrace) => const Center(
                        child: Padding(
                          padding: EdgeInsets.all(24),
                          child: Text(
                            'Не удалось загрузить фотографию.',
                            textAlign: TextAlign.center,
                            style: TextStyle(color: Colors.white70),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: IconButton.filledTonal(
                tooltip: 'Закрыть',
                style: IconButton.styleFrom(
                  foregroundColor: Colors.white,
                  backgroundColor: Colors.black54,
                ),
                onPressed: () => Navigator.of(context).pop(),
                icon: const Icon(Icons.close),
              ),
            ),
            if (caption != null && caption.isNotEmpty)
              Positioned(
                left: 16,
                right: 16,
                bottom: 14,
                child: IgnorePointer(
                  child: Center(
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: .58),
                        borderRadius: BorderRadius.circular(18),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 10,
                        ),
                        child: Text(
                          caption,
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white,
                            height: 1.35,
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            const Positioned(
              left: 12,
              top: 12,
              child: IgnorePointer(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: Colors.black45,
                    borderRadius: BorderRadius.all(Radius.circular(14)),
                  ),
                  child: Padding(
                    padding: EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.pinch, size: 18, color: Colors.white70),
                        SizedBox(width: 6),
                        Text(
                          'Масштабируйте двумя пальцами',
                          style: TextStyle(color: Colors.white70, fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ZoomBadge extends StatelessWidget {
  const _ZoomBadge();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Colors.black.withValues(alpha: .62),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: Colors.white38),
        ),
        child: const Padding(
          padding: EdgeInsets.all(8),
          child: Icon(Icons.fullscreen, color: Colors.white, size: 21),
        ),
      ),
    );
  }
}
