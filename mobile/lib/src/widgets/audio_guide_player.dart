import 'dart:async';

import 'package:flutter/material.dart';
import 'package:just_audio/just_audio.dart';

import '../theme/app_theme.dart';

class AudioGuidePlayer extends StatefulWidget {
  const AudioGuidePlayer({
    super.key,
    required this.url,
    required this.title,
    this.transcript,
  });

  final String url;
  final String title;
  final String? transcript;

  @override
  State<AudioGuidePlayer> createState() => _AudioGuidePlayerState();
}

class _AudioGuidePlayerState extends State<AudioGuidePlayer> {
  final AudioPlayer _player = AudioPlayer();
  final List<StreamSubscription<dynamic>> _subscriptions = [];

  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;
  bool _loading = true;
  String? _error;
  double _speed = 1;

  @override
  void initState() {
    super.initState();

    _subscriptions.add(_player.positionStream.listen((value) {
      if (mounted) setState(() => _position = value);
    }));
    _subscriptions.add(_player.durationStream.listen((value) {
      if (mounted && value != null) setState(() => _duration = value);
    }));
    _subscriptions.add(_player.playerStateStream.listen((_) {
      if (mounted) setState(() {});
    }));
    _subscriptions.add(_player.errorStream.listen((error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = error.message;
        });
      }
    }));

    _prepare();
  }

  Future<void> _prepare() async {
    try {
      await _player.setUrl(widget.url);
      if (mounted) setState(() => _loading = false);
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'Не удалось загрузить аудиогид: $error';
        });
      }
    }
  }

  Future<void> _toggle() async {
    if (_player.processingState == ProcessingState.completed) {
      await _player.seek(Duration.zero);
    }

    if (_player.playing) {
      await _player.pause();
    } else {
      await _player.play();
    }
  }

  Future<void> _seekBy(int seconds) async {
    final target = _position + Duration(seconds: seconds);
    final bounded = target < Duration.zero
        ? Duration.zero
        : target > _duration
            ? _duration
            : target;
    await _player.seek(bounded);
  }

  Future<void> _changeSpeed(double speed) async {
    await _player.setSpeed(speed);
    if (mounted) setState(() => _speed = speed);
  }

  @override
  void dispose() {
    for (final subscription in _subscriptions) {
      subscription.cancel();
    }
    _player.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final transcript = widget.transcript?.trim();
    final maxMilliseconds = _duration.inMilliseconds <= 0
        ? 1.0
        : _duration.inMilliseconds.toDouble();
    final currentMilliseconds = _position.inMilliseconds
        .clamp(0, maxMilliseconds.toInt())
        .toDouble();

    return Card(
      color: AppTheme.cream,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const CircleAvatar(
                  backgroundColor: AppTheme.green,
                  child: Icon(Icons.headphones, color: Colors.white),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Аудиогид',
                        style: TextStyle(
                          color: AppTheme.green,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        widget.title,
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            if (_loading)
              const LinearProgressIndicator()
            else if (_error != null)
              Text(_error!, style: const TextStyle(color: Colors.red))
            else ...[
              Slider(
                value: currentMilliseconds,
                max: maxMilliseconds,
                onChanged: (value) => _player.seek(
                  Duration(milliseconds: value.round()),
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(_format(_position)),
                    Text(_format(_duration)),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  IconButton(
                    tooltip: 'Назад на 15 секунд',
                    onPressed: () => _seekBy(-15),
                    icon: const Icon(Icons.replay_10),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    tooltip: _player.playing ? 'Пауза' : 'Воспроизвести',
                    onPressed: _toggle,
                    iconSize: 34,
                    icon: Icon(
                      _player.playing ? Icons.pause : Icons.play_arrow,
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    tooltip: 'Вперёд на 15 секунд',
                    onPressed: () => _seekBy(15),
                    icon: const Icon(Icons.forward_10),
                  ),
                  const SizedBox(width: 8),
                  PopupMenuButton<double>(
                    tooltip: 'Скорость воспроизведения',
                    onSelected: _changeSpeed,
                    itemBuilder: (_) => const [
                      PopupMenuItem(value: .75, child: Text('0,75×')),
                      PopupMenuItem(value: 1, child: Text('1×')),
                      PopupMenuItem(value: 1.25, child: Text('1,25×')),
                      PopupMenuItem(value: 1.5, child: Text('1,5×')),
                      PopupMenuItem(value: 2, child: Text('2×')),
                    ],
                    child: Padding(
                      padding: const EdgeInsets.all(8),
                      child: Text(
                        '${_speed.toStringAsFixed(_speed == 1 ? 0 : 2)}×',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
                ],
              ),
            ],
            if (transcript != null && transcript.isNotEmpty) ...[
              const Divider(height: 28),
              ExpansionTile(
                tilePadding: EdgeInsets.zero,
                childrenPadding: const EdgeInsets.only(bottom: 8),
                leading: const Icon(Icons.subject_outlined),
                title: const Text('Текст аудиогида'),
                children: [
                  SelectableText(
                    transcript,
                    style: const TextStyle(height: 1.5),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _format(Duration value) {
    final hours = value.inHours;
    final minutes = value.inMinutes.remainder(60).toString().padLeft(2, '0');
    final seconds = value.inSeconds.remainder(60).toString().padLeft(2, '0');
    return hours > 0 ? '$hours:$minutes:$seconds' : '$minutes:$seconds';
  }
}
