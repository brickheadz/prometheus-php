<?php

namespace Prometheus;

abstract class Metric
{
	public $namespace;
	public $name;
	public $subsystem;
	public $help;
	public $full_name;
	protected $values = [];
	protected $labels = [];
	protected $opts;

	public function __construct(array $opts = [])
	{
		$this->opts = $opts;
		$this->name = isset($opts['name']) ? $opts['name'] : '';
		$this->namespace = isset($opts['namespace']) ? $opts['namespace'] : '';
		$this->subsystem = isset($opts['subsystem']) ? $opts['subsystem'] : '';
		$this->help = isset($opts['help']) ? $opts['help'] : '';

		if (empty($this->name)) {
			throw new PrometheusException("A name is required for a metric");
		}
		if (empty($this->help)) {
			throw new PrometheusException("A help is required for a metric");
		}

		$this->full_name = implode('_', [$this->namespace, $this->subsystem, $this->name]);

		$this->values = [];
	}

	public function get(array $labels = [])
	{
		$hash = $this->hashLabels($labels);

		return $this->values[$hash] ?: $this->defaultValue();
	}

	protected function hashLabels(array $labels = []): string
	{
		$hash = md5(json_encode($labels, JSON_FORCE_OBJECT));
		$this->labels[$hash] = $labels;

		// TODO: save to memcached

		return $hash;
	}

	public function defaultValue()
	{
		return null;
	}

	public function serialize(): string
	{
		$tbr = [];
		$tbr [] = "# HELP " . $this->full_name . " " . $this->help;
		$tbr [] = "# TYPE " . $this->full_name . " " . $this->type();

		foreach ($this->values() as $val) {
			[$labels, $value] = $val;
			$label_pairs = [];
			$suffix = isset($labels['__suffix']) ? $labels['__suffix'] : '';
			unset($labels['__suffix']);

			foreach ($labels as $k => $v) {
				$v = str_replace("\"", "\\\"", $v);
				$v = str_replace("\n", "\\n", $v);
				$v = str_replace("\\", "\\\\", $v);
				$label_pairs [] = "$k=\"$v\"";
			}
			$tbr [] = $this->full_name . $suffix . "{" . implode(",", $label_pairs) . "} " . $value;
		}

		return implode("\n", $tbr);
	}

	abstract public function type(): string;

	public function values(): array
	{
		$values = [];
		foreach ($this->values as $hash => $val) {
			$values [] = [$this->labels[$hash], $val];
		}

		return $values;
	}

	public function getLabels(): array
	{
		/* For debugging only */
		return $this->labels;
	}
}
