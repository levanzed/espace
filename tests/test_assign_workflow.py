from app.services.assign_workflow import (
    resolve_accept_submission_statement,
    tokenize_fileurls,
)


def test_tokenize_fileurls_nested_plugins() -> None:
    payload = {
        "lastattempt": {
            "submission": {
                "plugins": [
                    {
                        "fileareas": [
                            {
                                "files": [
                                    {"fileurl": "https://moodle/pluginfile.php/1/a", "filename": "a.pdf"},
                                ]
                            }
                        ]
                    }
                ]
            }
        }
    }
    out = tokenize_fileurls(payload, "tok123")
    url = out["lastattempt"]["submission"]["plugins"][0]["fileareas"][0]["files"][0]["fileurl"]
    assert "token=tok123" in url


def test_resolve_statement_required() -> None:
    assignment = {"requiresubmissionstatement": 1}
    assert resolve_accept_submission_statement(assignment, True) == 1


def test_resolve_statement_not_required() -> None:
    assignment = {"requiresubmissionstatement": 0}
    assert resolve_accept_submission_statement(assignment, False) == 0
